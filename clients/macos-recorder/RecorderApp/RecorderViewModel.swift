import Foundation
import CryptoKit
import Combine

private let serverUrlDefaultsKey = "recorder_server_url"

@MainActor
final class RecorderViewModel: ObservableObject {
    @Published var isAuthenticated = false
    @Published var isClockedIn = false
    @Published var isRecording = false
    @Published var isAutoCapturing = false
    @Published var recordingDuration = 0
    @Published var pendingUploads = 0
    @Published var nextCaptureIn = 0       // seconds until next auto-capture
    @Published var statusMessage = ""
    @Published var errorMessage: String?
    @Published var todaysRecordings: [ServerRecordingSummary] = []
    @Published var savedServerUrl: String

    private var apiClient: RecorderAPIClient
    private let queue = OfflineUploadQueue()
    private var syncWorker: SyncWorker
    private let recorder = ScreenRecorderService()
    private var recordingTimer: Timer?
    private var schedulerTask: Task<Void, Never>?

    private let captureIntervalSeconds = 3600   // 60 minutes between captures
    private let captureDurationSeconds = 30     // 30-second clip per capture

    init() {
        let saved = UserDefaults.standard.string(forKey: serverUrlDefaultsKey) ?? "https://app.itsworkplace.com"
        savedServerUrl = saved
        let url = URL(string: saved) ?? URL(string: "https://app.itsworkplace.com")!
        apiClient = RecorderAPIClient(baseUrl: url)
        syncWorker = SyncWorker(apiClient: apiClient, queueStore: queue)
        isAuthenticated = apiClient.isAuthenticated
    }

    // MARK: - Auth

    func login(email: String, password: String, subdomain: String, serverUrl: String) async {
        errorMessage = nil
        let trimmed = serverUrl.trimmingCharacters(in: .whitespaces)
        guard let url = URL(string: trimmed), url.scheme != nil else {
            errorMessage = "Invalid server URL"
            return
        }
        UserDefaults.standard.set(trimmed, forKey: serverUrlDefaultsKey)
        savedServerUrl = trimmed
        apiClient.updateBaseUrl(url)
        syncWorker = SyncWorker(apiClient: apiClient, queueStore: queue)

        do {
            try await apiClient.login(email: email, password: password, companySubdomain: subdomain)
            isAuthenticated = true
            await refreshStatus()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func logout() {
        stopAutoRecordScheduler()
        apiClient.logout()
        isAuthenticated = false
        isClockedIn = false
        isRecording = false
        isAutoCapturing = false
        recordingTimer?.invalidate()
        recordingTimer = nil
        todaysRecordings = []
        statusMessage = ""
        errorMessage = nil
    }

    // MARK: - Time tracking

    func clockIn() async {
        errorMessage = nil
        do {
            try await apiClient.timeIn()
            isClockedIn = true
            statusMessage = "Clocked in"
            // Fire first capture immediately, then every hour
            startAutoRecordScheduler(initialDelay: 0)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func clockOut() async {
        errorMessage = nil
        stopAutoRecordScheduler()
        if isRecording { await stopRecording() }
        do {
            try await apiClient.timeOut()
            isClockedIn = false
            statusMessage = "Clocked out"
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    // MARK: - Auto-record scheduler

    /// Starts the hourly auto-capture loop.
    /// - Parameter initialDelay: seconds before the first capture fires.
    ///   Pass 0 when the user explicitly clocks in (matches Windows behaviour).
    ///   Pass captureIntervalSeconds when restoring state on app launch.
    func startAutoRecordScheduler(initialDelay: Int) {
        stopAutoRecordScheduler()
        schedulerTask = Task { @MainActor in
            var delay = initialDelay
            while !Task.isCancelled {
                // Countdown display
                if delay > 0 {
                    for remaining in stride(from: delay, through: 1, by: -1) {
                        guard !Task.isCancelled else { return }
                        self.nextCaptureIn = remaining
                        try? await Task.sleep(nanoseconds: 1_000_000_000)
                    }
                }
                self.nextCaptureIn = 0
                guard !Task.isCancelled else { return }

                await self.performAutoCapture()

                delay = self.captureIntervalSeconds
            }
        }
    }

    func stopAutoRecordScheduler() {
        schedulerTask?.cancel()
        schedulerTask = nil
        nextCaptureIn = 0
        isAutoCapturing = false
    }

    private func performAutoCapture() async {
        guard isClockedIn, !isRecording else { return }
        isAutoCapturing = true
        statusMessage = "Auto-capturing screen (30s)…"

        await startRecording()

        // Hold for 30 seconds; Task cancellation interrupts the sleep early
        try? await Task.sleep(nanoseconds: UInt64(captureDurationSeconds) * 1_000_000_000)

        if isRecording { await stopRecording() }
        isAutoCapturing = false

        if !Task.isCancelled { await syncUploads() }
    }

    // MARK: - Recording

    func startRecording() async {
        errorMessage = nil
        do {
            try await recorder.start()
            isRecording = true
            recordingDuration = 0
            recordingTimer = Timer.scheduledTimer(withTimeInterval: 1, repeats: true) { [weak self] _ in
                Task { @MainActor [weak self] in self?.recordingDuration += 1 }
            }
            if !isAutoCapturing { statusMessage = "Recording started" }
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func stopRecording() async {
        recordingTimer?.invalidate()
        recordingTimer = nil
        errorMessage = nil
        do {
            let (url, duration) = try await recorder.stop()
            isRecording = false
            let uploadId = UUID().uuidString
            let date = apiClient.getCompanyDateYmd()
            let checksum = computeSHA256(url: url)
            let item = OfflineQueueItem(
                id: UUID(),
                uploadId: uploadId,
                date: date,
                duration: duration,
                filePath: url.path,
                checksum: checksum,
                retryCount: 0,
                lastError: nil
            )
            try queue.enqueue(item)
            pendingUploads = queue.count()
            if !isAutoCapturing { statusMessage = "Recording saved — \(pendingUploads) file(s) queued" }
        } catch {
            isRecording = false
            errorMessage = error.localizedDescription
        }
    }

    // MARK: - Sync

    func syncUploads() async {
        errorMessage = nil
        if !isAutoCapturing { statusMessage = "Syncing uploads…" }
        await syncWorker.syncPendingUploads()
        pendingUploads = queue.count()
        await refreshTodaysRecordings()
        if !isAutoCapturing {
            statusMessage = pendingUploads == 0 ? "All uploads synced" : "\(pendingUploads) upload(s) still pending"
        }
    }

    // MARK: - Refresh

    func refreshStatus() async {
        do {
            let status = try await apiClient.fetchTimeTrackingStatus()
            isClockedIn = status.clockedIn
            // App launched with an active session — wait a full hour before the next capture
            if isClockedIn && schedulerTask == nil {
                startAutoRecordScheduler(initialDelay: captureIntervalSeconds)
            }
        } catch { /* non-fatal */ }
        pendingUploads = queue.count()
        await refreshTodaysRecordings()
    }

    private func refreshTodaysRecordings() async {
        do {
            todaysRecordings = try await apiClient.fetchTodaysRecordings()
        } catch { /* non-fatal */ }
    }

    // MARK: - Helpers

    private func computeSHA256(url: URL) -> String? {
        guard let data = try? Data(contentsOf: url) else { return nil }
        let digest = SHA256.hash(data: data)
        return digest.map { String(format: "%02x", $0) }.joined()
    }
}
