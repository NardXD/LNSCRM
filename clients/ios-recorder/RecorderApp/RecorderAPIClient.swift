import Foundation

// MARK: - Models

struct TimeTrackingRecord: Decodable {
    let date: String?
    let timeIn: String?

    enum CodingKeys: String, CodingKey {
        case date
        case timeIn = "time_in"
    }
}

struct ServerRecordingSummary: Decodable, Identifiable {
    let id: Int
    let uploadId: String?
    let date: String
    let screenRecordingDuration: Int?
    let status: String
    let syncStatus: String

    enum CodingKeys: String, CodingKey {
        case id
        case uploadId = "upload_id"
        case date
        case screenRecordingDuration = "screen_recording_duration"
        case status
        case syncStatus = "sync_status"
    }
}

enum RecorderError: LocalizedError {
    case notAuthenticated
    case httpError(Int, String)
    case serverRejected(String)

    var errorDescription: String? {
        switch self {
        case .notAuthenticated:
            return "Not authenticated. Please log in."
        case .httpError(let code, let msg):
            return "HTTP \(code): \(msg)"
        case .serverRejected(let msg):
            return msg
        }
    }
}

// MARK: - Client

final class RecorderAPIClient {
    private let defaults = UserDefaults.standard

    private let tokenKey = "recorder_token"
    private let timezoneKey = "recorder_company_timezone"
    private let deviceIdKey = "recorder_device_id"

    private(set) var baseUrl: URL

    init(baseUrl: URL) {
        self.baseUrl = baseUrl
    }

    func updateBaseUrl(_ url: URL) {
        baseUrl = url
    }

    var isAuthenticated: Bool { token != nil }

    private var token: String? {
        get { defaults.string(forKey: tokenKey) }
        set {
            if let v = newValue { defaults.set(v, forKey: tokenKey) }
            else { defaults.removeObject(forKey: tokenKey) }
        }
    }

    private var companyTimezone: String {
        get { defaults.string(forKey: timezoneKey) ?? "UTC" }
        set { defaults.set(newValue, forKey: timezoneKey) }
    }

    func getOrCreateDeviceId() -> String {
        if let existing = defaults.string(forKey: deviceIdKey), !existing.isEmpty {
            return existing
        }
        let id = "ios-\(UUID().uuidString.prefix(8).lowercased())"
        defaults.set(id, forKey: deviceIdKey)
        return id
    }

    // MARK: - Auth

    func login(email: String, password: String, companySubdomain: String) async throws {
        let deviceId = getOrCreateDeviceId()
        let payload: [String: String] = [
            "email": email,
            "password": password,
            "company_subdomain": companySubdomain,
            "device_id": deviceId,
            "platform": "ios"
        ]

        var request = URLRequest(url: baseUrl.appendingPathComponent("/api/recorder/login"))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: payload)

        let (data, response) = try await URLSession.shared.data(for: request)
        let statusCode = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200...299).contains(statusCode) else {
            let msg = String(data: data, encoding: .utf8).map { String($0.prefix(200)) } ?? "Unknown"
            throw RecorderError.httpError(statusCode, msg)
        }

        struct LoginResponse: Decodable {
            let token: String
            let company: CompanyInfo?
            struct CompanyInfo: Decodable {
                let timezone: String?
            }
        }

        let decoded = try JSONDecoder().decode(LoginResponse.self, from: data)
        token = decoded.token

        let tz = decoded.company?.timezone.flatMap {
            let t = $0.trimmingCharacters(in: .whitespaces)
            return t.isEmpty ? nil : t
        } ?? "UTC"
        companyTimezone = tz
    }

    func logout() {
        token = nil
        defaults.removeObject(forKey: timezoneKey)
    }

    // MARK: - Timezone / Time

    func readCompanyTimezone() -> String { companyTimezone }

    func getCompanyDateYmd() -> String {
        let tz = TimeZone(identifier: companyTimezone) ?? .gmt
        var cal = Calendar(identifier: .gregorian)
        cal.timeZone = tz
        let now = Date()
        let y = cal.component(.year, from: now)
        let mo = cal.component(.month, from: now)
        let d = cal.component(.day, from: now)
        return String(format: "%04d-%02d-%02d", y, mo, d)
    }

    func getCompanyNowForApi() -> (date: String, time: String) {
        let tz = TimeZone(identifier: companyTimezone) ?? .gmt
        var cal = Calendar(identifier: .gregorian)
        cal.timeZone = tz
        let now = Date()
        let y = cal.component(.year, from: now)
        let mo = cal.component(.month, from: now)
        let d = cal.component(.day, from: now)
        let h = cal.component(.hour, from: now)
        let mi = cal.component(.minute, from: now)
        let s = cal.component(.second, from: now)
        return (
            date: String(format: "%04d-%02d-%02d", y, mo, d),
            time: String(format: "%02d:%02d:%02d", h, mi, s)
        )
    }

    // MARK: - Time Tracking

    func timeIn() async throws {
        let (date, time) = getCompanyNowForApi()
        try await sendJSON(path: "/api/recorder/time-tracking/time-in", payload: ["date": date, "time": time])
    }

    func timeOut() async throws {
        let (date, time) = getCompanyNowForApi()
        try await sendJSON(path: "/api/recorder/time-tracking/time-out", payload: ["date": date, "time": time])
    }

    func fetchTimeTrackingStatus() async throws -> (clockedIn: Bool, record: TimeTrackingRecord?) {
        let data = try await getAuthenticated(path: "/api/recorder/time-tracking/status")

        struct StatusResponse: Decodable {
            let clockedIn: Bool?
            let record: TimeTrackingRecord?
            enum CodingKeys: String, CodingKey {
                case clockedIn = "clocked_in"
                case record
            }
        }

        let decoded = try JSONDecoder().decode(StatusResponse.self, from: data)
        return (clockedIn: decoded.clockedIn ?? false, record: decoded.record)
    }

    // MARK: - Recordings

    func fetchTodaysRecordings() async throws -> [ServerRecordingSummary] {
        let data = try await getAuthenticated(path: "/api/recorder/recordings/today")

        struct RecordingsResponse: Decodable {
            let recordings: [ServerRecordingSummary]
        }

        let decoded = try JSONDecoder().decode(RecordingsResponse.self, from: data)
        return decoded.recordings
    }

    // MARK: - Uploads

    func startUpload(uploadId: String, date: String, duration: Int, checksum: String?) async throws {
        var payload: [String: Any] = [
            "upload_id": uploadId,
            "date": date,
            "duration": duration
        ]
        if let checksum { payload["upload_checksum"] = checksum }
        try await sendJSON(path: "/api/recorder/uploads/start", payload: payload)
    }

    func chunkUpload(uploadId: String, fileURL: URL) async throws {
        guard let token else { throw RecorderError.notAuthenticated }

        let boundary = "Boundary-\(UUID().uuidString)"
        var request = URLRequest(url: baseUrl.appendingPathComponent("/api/recorder/uploads/chunk"))
        request.httpMethod = "POST"
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")

        let fileData = try Data(contentsOf: fileURL)
        var body = Data()
        let crlf = "\r\n"

        func appendString(_ s: String) {
            if let d = s.data(using: .utf8) { body.append(d) }
        }

        appendString("--\(boundary)\(crlf)")
        appendString("Content-Disposition: form-data; name=\"upload_id\"\(crlf)\(crlf)")
        appendString(uploadId)
        appendString(crlf)

        let filename = fileURL.lastPathComponent
        appendString("--\(boundary)\(crlf)")
        appendString("Content-Disposition: form-data; name=\"recording\"; filename=\"\(filename)\"\(crlf)")
        appendString("Content-Type: video/mp4\(crlf)\(crlf)")
        body.append(fileData)
        appendString(crlf)
        appendString("--\(boundary)--\(crlf)")

        request.httpBody = body

        let (responseData, response) = try await URLSession.shared.data(for: request)
        let statusCode = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200...299).contains(statusCode) else {
            let msg = String(data: responseData, encoding: .utf8).map { String($0.prefix(200)) } ?? "Unknown"
            throw RecorderError.httpError(statusCode, msg)
        }
    }

    func finalizeUpload(uploadId: String, duration: Int, checksum: String?) async throws {
        var payload: [String: Any] = [
            "upload_id": uploadId,
            "duration": duration
        ]
        if let checksum { payload["upload_checksum"] = checksum }
        try await sendJSON(path: "/api/recorder/uploads/finalize", payload: payload)
    }

    // MARK: - Private helpers

    private func getAuthenticated(path: String) async throws -> Data {
        guard let token else { throw RecorderError.notAuthenticated }

        var request = URLRequest(url: baseUrl.appendingPathComponent(path))
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")

        let (data, response) = try await URLSession.shared.data(for: request)
        let statusCode = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200...299).contains(statusCode) else {
            let msg = String(data: data, encoding: .utf8).map { String($0.prefix(200)) } ?? "Unknown"
            throw RecorderError.httpError(statusCode, msg)
        }
        return data
    }

    private func sendJSON(path: String, payload: [String: Any]) async throws {
        guard let token else { throw RecorderError.notAuthenticated }

        var request = URLRequest(url: baseUrl.appendingPathComponent(path))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.httpBody = try JSONSerialization.data(withJSONObject: payload)

        let (data, response) = try await URLSession.shared.data(for: request)
        let statusCode = (response as? HTTPURLResponse)?.statusCode ?? 0
        guard (200...299).contains(statusCode) else {
            let msg = String(data: data, encoding: .utf8).map { String($0.prefix(200)) } ?? "Unknown"
            throw RecorderError.httpError(statusCode, msg)
        }

        if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
           let success = json["success"] as? Bool, !success {
            let msg = json["message"] as? String ?? "Request rejected by server"
            throw RecorderError.serverRejected(msg)
        }
    }
}
