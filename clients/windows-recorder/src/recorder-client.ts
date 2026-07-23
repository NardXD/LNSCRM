import { randomUUID } from 'node:crypto';
import { mkdir, readFile, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';

export type QueueItem = {
  uploadId: string;
  date: string;
  duration: number;
  filePath: string;
  checksum?: string;
  retryCount: number;
  lastError?: string;
};

export type LoginPayload = {
  email: string;
  password: string;
  companySubdomain: string;
  deviceId: string;
};

export type ClockStamp = {
  date: string;
  time: string;
  display: string;
};

export type TimeTrackingRecord = {
  date?: string;
  time_in?: string | null;
};

export type ServerRecordingSummary = {
  id: number;
  upload_id: string | null;
  date: string;
  screen_recording_duration: number | null;
  status: string;
  sync_status: string;
  queued_at: string | null;
  uploaded_at: string | null;
  created_at: string | null;
};

export class RecorderClient {
  private readonly apiBaseUrl: string;
  private readonly storageDir: string;
  private readonly queueFile: string;
  private readonly tokenFile: string;
  private readonly timezoneFile: string;

  public constructor(apiBaseUrl: string) {
    this.apiBaseUrl = apiBaseUrl.replace(/\/$/, '');
    this.storageDir = path.join(os.homedir(), '.itsworkplace-recorder');
    this.queueFile = path.join(this.storageDir, 'queue.json');
    this.tokenFile = path.join(this.storageDir, 'token.txt');
    this.timezoneFile = path.join(this.storageDir, 'company-timezone.txt');
  }

  public static async getOrCreateDeviceId(): Promise<string> {
    const storageDir = path.join(os.homedir(), '.itsworkplace-recorder');
    const deviceFile = path.join(storageDir, 'device-id.txt');
    await mkdir(storageDir, { recursive: true });
    try {
      const existing = (await readFile(deviceFile, 'utf-8')).trim();
      if (existing.length > 0) {
        return existing;
      }
    } catch {
      // File missing or unreadable; create below.
    }

    const id = `win-${randomUUID().slice(0, 8)}`;
    await writeFile(deviceFile, id, 'utf-8');

    return id;
  }

  public getApiBaseUrl(): string {
    return this.apiBaseUrl;
  }

  public async initialize(): Promise<void> {
    await mkdir(this.storageDir, { recursive: true });
    try {
      await stat(this.queueFile);
    } catch {
      await writeFile(this.queueFile, '[]', 'utf-8');
    }
  }

  public async login(payload: LoginPayload): Promise<void> {
    const response = await fetch(`${this.apiBaseUrl}/api/recorder/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: payload.email,
        password: payload.password,
        company_subdomain: payload.companySubdomain,
        device_id: payload.deviceId,
        platform: 'windows',
      }),
    });

    if (!response.ok) {
      const text = await response.text();
      throw new Error(`Login failed (${response.status}): ${text.slice(0, 200)}`);
    }

    const body = (await response.json()) as {
      token: string;
      company?: { timezone?: string | null };
    };
    await writeFile(this.tokenFile, body.token, 'utf-8');
    const tz =
      body.company?.timezone && String(body.company.timezone).trim().length > 0
        ? String(body.company.timezone).trim()
        : 'UTC';
    await writeFile(this.timezoneFile, tz, 'utf-8');
  }

  public async logout(): Promise<void> {
    await rm(this.tokenFile, { force: true });
    await rm(this.timezoneFile, { force: true });
  }

  public async readCompanyTimezone(): Promise<string> {
    try {
      const raw = (await readFile(this.timezoneFile, 'utf-8')).trim();
      if (raw.length > 0) {
        return raw;
      }
    } catch {
      // Missing file; fall through.
    }

    return 'UTC';
  }

  public async getCompanyDateYmd(): Promise<string> {
    const tz = await this.readCompanyTimezone();

    try {
      return new Intl.DateTimeFormat('en-CA', {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      }).format(new Date());
    } catch {
      return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'UTC',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      }).format(new Date());
    }
  }

  public async getCompanyNowForApi(): Promise<{ date: string; time: string }> {
    const tz = await this.readCompanyTimezone();

    const formatParts = (timeZone: string): { date: string; time: string } => {
      const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
      }).formatToParts(new Date());
      const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '00';
      const date = `${get('year')}-${get('month')}-${get('day')}`;
      const time = `${get('hour')}:${get('minute')}:${get('second')}`;

      return { date, time };
    };

    try {
      return formatParts(tz);
    } catch {
      return formatParts('UTC');
    }
  }

  public async timeIn(): Promise<void> {
    const { date, time } = await this.getCompanyNowForApi();
    const token = (await readFile(this.tokenFile, 'utf-8')).trim();
    const response = await fetch(`${this.apiBaseUrl}/api/recorder/time-tracking/time-in`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ date, time }),
    });
    const body = (await response.json()) as { success?: boolean; message?: string };
    if (!response.ok) {
      const msg = body.message ?? JSON.stringify(body);
      throw new Error(`Time in failed (${response.status}): ${msg}`);
    }
    if (body.success === false) {
      throw new Error(body.message ?? 'Time in was rejected by the server.');
    }
  }

  public async timeOut(): Promise<void> {
    const { date, time } = await this.getCompanyNowForApi();
    const token = (await readFile(this.tokenFile, 'utf-8')).trim();
    const response = await fetch(`${this.apiBaseUrl}/api/recorder/time-tracking/time-out`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ date, time }),
    });
    const body = (await response.json()) as { success?: boolean; message?: string };
    if (!response.ok) {
      const msg = body.message ?? JSON.stringify(body);
      throw new Error(`Time out failed (${response.status}): ${msg}`);
    }
    if (body.success === false) {
      throw new Error(body.message ?? 'Time out was rejected by the server.');
    }
  }

  public async fetchTimeTrackingStatus(): Promise<{
    clockedIn: boolean;
    record: TimeTrackingRecord | null;
  }> {
    const token = (await readFile(this.tokenFile, 'utf-8')).trim();
    const response = await fetch(`${this.apiBaseUrl}/api/recorder/time-tracking/status`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    if (!response.ok) {
      const text = await response.text();
      throw new Error(`Time tracking status failed (${response.status}): ${text.slice(0, 200)}`);
    }

    const body = (await response.json()) as {
      success?: boolean;
      clocked_in?: boolean;
      record?: TimeTrackingRecord | null;
    };

    return {
      clockedIn: Boolean(body.clocked_in),
      record: body.record ?? null,
    };
  }

  public async fetchTodaysRecordings(): Promise<ServerRecordingSummary[]> {
    const token = (await readFile(this.tokenFile, 'utf-8')).trim();
    const response = await fetch(`${this.apiBaseUrl}/api/recorder/recordings/today`, {
      headers: { Authorization: `Bearer ${token}` },
    });

    if (!response.ok) {
      const text = await response.text();
      throw new Error(`List recordings failed (${response.status}): ${text.slice(0, 200)}`);
    }

    const body = (await response.json()) as { success: boolean; recordings: ServerRecordingSummary[] };

    return body.recordings ?? [];
  }

  public async getQueue(): Promise<QueueItem[]> {
    return this.readQueue();
  }

  public async queueRecording(item: Omit<QueueItem, 'retryCount'>): Promise<void> {
    const queue = await this.readQueue();
    queue.push({ ...item, retryCount: 0 });
    await this.writeQueue(queue);
  }

  public async syncQueue(): Promise<void> {
    const queue = await this.readQueue();
    const syncedUploadIds: string[] = [];

    for (const item of queue) {
      try {
        await this.syncItem(item);
        syncedUploadIds.push(item.uploadId);
      } catch (error) {
        item.retryCount += 1;
        item.lastError = error instanceof Error ? error.message : 'Unknown upload failure';
      }
    }

    const remaining = queue.filter((item) => !syncedUploadIds.includes(item.uploadId));
    await this.writeQueue(remaining);
  }

  private async syncItem(item: QueueItem): Promise<void> {
    const token = (await readFile(this.tokenFile, 'utf-8')).trim();
    const commonHeaders = {
      Authorization: `Bearer ${token}`,
    };

    const startResponse = await fetch(`${this.apiBaseUrl}/api/recorder/uploads/start`, {
      method: 'POST',
      headers: {
        ...commonHeaders,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        upload_id: item.uploadId,
        date: item.date,
        duration: item.duration,
        upload_checksum: item.checksum,
      }),
    });
    if (!startResponse.ok) {
      const text = await startResponse.text();
      throw new Error(`Upload start failed (${startResponse.status}): ${text.slice(0, 200)}`);
    }

    const fileBuffer = await readFile(item.filePath);
    const uploadForm = new FormData();
    uploadForm.set('upload_id', item.uploadId);
    uploadForm.set('recording', new Blob([fileBuffer]), path.basename(item.filePath));

    const chunkResponse = await fetch(`${this.apiBaseUrl}/api/recorder/uploads/chunk`, {
      method: 'POST',
      headers: commonHeaders,
      body: uploadForm,
    });
    if (!chunkResponse.ok) {
      const text = await chunkResponse.text();
      throw new Error(`Upload chunk failed (${chunkResponse.status}): ${text.slice(0, 200)}`);
    }

    const finalizeResponse = await fetch(`${this.apiBaseUrl}/api/recorder/uploads/finalize`, {
      method: 'POST',
      headers: {
        ...commonHeaders,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        upload_id: item.uploadId,
        duration: item.duration,
        upload_checksum: item.checksum,
      }),
    });
    if (!finalizeResponse.ok) {
      const text = await finalizeResponse.text();
      throw new Error(`Upload finalize failed (${finalizeResponse.status}): ${text.slice(0, 200)}`);
    }

    await rm(item.filePath, { force: true });
  }

  private async readQueue(): Promise<QueueItem[]> {
    const raw = await readFile(this.queueFile, 'utf-8');
    return JSON.parse(raw) as QueueItem[];
  }

  private async writeQueue(queue: QueueItem[]): Promise<void> {
    await writeFile(this.queueFile, JSON.stringify(queue, null, 2), 'utf-8');
  }
}
