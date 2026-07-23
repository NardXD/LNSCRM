import Foundation

struct OfflineQueueItem: Codable, Identifiable {
    let id: UUID
    let uploadId: String
    let date: String
    let duration: Int
    let filePath: String
    let checksum: String?
    var retryCount: Int
    var lastError: String?
}

final class OfflineUploadQueue {
    private let queueUrl: URL

    init() {
        let documents = FileManager.default.urls(for: .documentDirectory, in: .userDomainMask).first!
        queueUrl = documents.appendingPathComponent("recorder-upload-queue.json")
    }

    func load() throws -> [OfflineQueueItem] {
        guard FileManager.default.fileExists(atPath: queueUrl.path) else { return [] }
        let data = try Data(contentsOf: queueUrl)
        return try JSONDecoder().decode([OfflineQueueItem].self, from: data)
    }

    func save(items: [OfflineQueueItem]) throws {
        let encoded = try JSONEncoder().encode(items)
        try encoded.write(to: queueUrl, options: [.atomic])
    }

    func enqueue(_ item: OfflineQueueItem) throws {
        var items = try load()
        items.append(item)
        try save(items: items)
    }

    func count() -> Int {
        (try? load())?.count ?? 0
    }
}
