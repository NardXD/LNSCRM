import Foundation

final class SyncWorker {
    private let apiClient: RecorderAPIClient
    private let queueStore: OfflineUploadQueue

    init(apiClient: RecorderAPIClient, queueStore: OfflineUploadQueue) {
        self.apiClient = apiClient
        self.queueStore = queueStore
    }

    func syncPendingUploads() async {
        do {
            var items = try queueStore.load()
            guard !items.isEmpty else { return }

            var successfulIds = Set<UUID>()

            for index in items.indices {
                do {
                    let item = items[index]
                    let fileURL = URL(fileURLWithPath: item.filePath)

                    try await apiClient.startUpload(
                        uploadId: item.uploadId,
                        date: item.date,
                        duration: item.duration,
                        checksum: item.checksum
                    )
                    try await apiClient.chunkUpload(uploadId: item.uploadId, fileURL: fileURL)
                    try await apiClient.finalizeUpload(
                        uploadId: item.uploadId,
                        duration: item.duration,
                        checksum: item.checksum
                    )

                    try? FileManager.default.removeItem(at: fileURL)
                    successfulIds.insert(item.id)
                } catch {
                    items[index].retryCount += 1
                    items[index].lastError = error.localizedDescription
                    print("[SyncWorker] upload failed for \(items[index].uploadId): \(error)")
                }
            }

            let remaining = items.filter { !successfulIds.contains($0.id) }
            try queueStore.save(items: remaining)
        } catch {
            print("[SyncWorker] queue sync error: \(error)")
        }
    }
}
