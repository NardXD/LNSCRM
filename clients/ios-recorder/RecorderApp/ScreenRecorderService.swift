import Foundation
import ReplayKit
import AVFoundation
import UIKit

final class ScreenRecorderService {
    private let recorder = RPScreenRecorder.shared()

    // Serialises all AVAssetWriter calls to avoid data races.
    private let writerQueue = DispatchQueue(label: "com.itsworkplace.recorder.writer", qos: .userInitiated)

    private var assetWriter: AVAssetWriter?
    private var videoInput: AVAssetWriterInput?
    private var audioInput: AVAssetWriterInput?
    private var outputURL: URL?
    private var sessionStarted = false
    private var startDate: Date?

    var isRecording: Bool { recorder.isRecording }

    // MARK: - Start

    func start() async throws {
        let url = FileManager.default.temporaryDirectory
            .appendingPathComponent("\(UUID().uuidString).mp4")

        // Screen dimensions must be read on the main thread.
        let (width, height): (Int, Int) = await MainActor.run {
            let scale = UIScreen.main.scale
            let size = UIScreen.main.bounds.size
            return (Int(size.width * scale), Int(size.height * scale))
        }

        let writer = try AVAssetWriter(outputURL: url, fileType: .mp4)

        let videoSettings: [String: Any] = [
            AVVideoCodecKey: AVVideoCodecType.h264,
            AVVideoWidthKey: width,
            AVVideoHeightKey: height,
            AVVideoCompressionPropertiesKey: [AVVideoAverageBitRateKey: 2_000_000]
        ]
        let videoIn = AVAssetWriterInput(mediaType: .video, outputSettings: videoSettings)
        videoIn.expectsMediaDataInRealTime = true

        let audioSettings: [String: Any] = [
            AVFormatIDKey: kAudioFormatMPEG4AAC,
            AVSampleRateKey: 44100,
            AVNumberOfChannelsKey: 2,
            AVEncoderBitRateKey: 128_000
        ]
        let audioIn = AVAssetWriterInput(mediaType: .audio, outputSettings: audioSettings)
        audioIn.expectsMediaDataInRealTime = true

        if writer.canAdd(videoIn) { writer.add(videoIn) }
        if writer.canAdd(audioIn) { writer.add(audioIn) }

        self.outputURL = url
        self.assetWriter = writer
        self.videoInput = videoIn
        self.audioInput = audioIn
        self.sessionStarted = false
        self.startDate = Date()

        try await withCheckedThrowingContinuation { (continuation: CheckedContinuation<Void, Error>) in
            recorder.startCapture(handler: { [weak self] buffer, type, error in
                if let error {
                    print("[ScreenRecorder] buffer error: \(error)")
                    return
                }
                self?.handleBuffer(buffer, type: type)
            }, completionHandler: { error in
                if let error {
                    continuation.resume(throwing: error)
                } else {
                    continuation.resume()
                }
            })
        }
    }

    // MARK: - Buffer handler

    private func handleBuffer(_ buffer: CMSampleBuffer, type: RPSampleBufferType) {
        writerQueue.async { [weak self] in
            guard let self, let writer = self.assetWriter else { return }

            if !self.sessionStarted, writer.status == .unknown {
                writer.startWriting()
                writer.startSession(atSourceTime: CMSampleBufferGetPresentationTimeStamp(buffer))
                self.sessionStarted = true
            }

            guard self.sessionStarted, writer.status == .writing else { return }

            switch type {
            case .video:
                if self.videoInput?.isReadyForMoreMediaData == true {
                    self.videoInput?.append(buffer)
                }
            case .audioApp:
                if self.audioInput?.isReadyForMoreMediaData == true {
                    self.audioInput?.append(buffer)
                }
            default:
                break
            }
        }
    }

    // MARK: - Stop

    func stop() async throws -> (url: URL, duration: Int) {
        let elapsed = Int(Date().timeIntervalSince(startDate ?? Date()))

        return try await withCheckedThrowingContinuation { continuation in
            recorder.stopCapture { [weak self] error in
                guard let self else {
                    continuation.resume(throwing: RecorderError.httpError(0, "Recorder deallocated"))
                    return
                }
                if let error {
                    continuation.resume(throwing: error)
                    return
                }
                self.writerQueue.async {
                    self.videoInput?.markAsFinished()
                    self.audioInput?.markAsFinished()
                    self.assetWriter?.finishWriting {
                        guard let url = self.outputURL else {
                            continuation.resume(throwing: URLError(.cannotCreateFile))
                            return
                        }
                        continuation.resume(returning: (url: url, duration: elapsed))
                    }
                }
            }
        }
    }
}
