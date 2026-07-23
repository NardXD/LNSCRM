import Foundation
import ScreenCaptureKit
import AVFoundation

// Requires macOS 13.0+ (ScreenCaptureKit audio output type added in 13.0)
final class ScreenRecorderService: NSObject, SCStreamOutput, SCStreamDelegate {

    private let writerQueue = DispatchQueue(
        label: "com.itsworkplace.macos.recorder.writer",
        qos: .userInitiated
    )

    private var stream: SCStream?
    private var assetWriter: AVAssetWriter?
    private var videoInput: AVAssetWriterInput?
    private var audioInput: AVAssetWriterInput?
    private var outputURL: URL?
    private var sessionStarted = false
    private var startDate: Date?

    var isRecording: Bool { stream != nil }

    // MARK: - Start

    func start() async throws {
        let url = FileManager.default.temporaryDirectory
            .appendingPathComponent("\(UUID().uuidString).mp4")

        let available = try await SCShareableContent.excludingDesktopWindows(false, onScreenWindowsOnly: true)
        guard let display = available.displays.first else {
            throw RecorderError.serverRejected("No display available for capture")
        }

        let width = display.width
        let height = display.height

        let writer = try AVAssetWriter(outputURL: url, fileType: .mp4)

        let videoSettings: [String: Any] = [
            AVVideoCodecKey: AVVideoCodecType.h264,
            AVVideoWidthKey: width,
            AVVideoHeightKey: height,
            AVVideoCompressionPropertiesKey: [AVVideoAverageBitRateKey: 4_000_000]
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

        let config = SCStreamConfiguration()
        config.width = width
        config.height = height
        config.minimumFrameInterval = CMTime(value: 1, timescale: 30)
        config.capturesAudio = true
        config.sampleRate = 44100
        config.channelCount = 2

        let filter = SCContentFilter(display: display, excludingWindows: [])
        let captureStream = SCStream(filter: filter, configuration: config, delegate: self)

        try captureStream.addStreamOutput(self, type: .screen, sampleHandlerQueue: writerQueue)
        try captureStream.addStreamOutput(self, type: .audio, sampleHandlerQueue: writerQueue)

        self.stream = captureStream
        try await captureStream.startCapture()
    }

    // MARK: - SCStreamOutput

    func stream(
        _ stream: SCStream,
        didOutputSampleBuffer sampleBuffer: CMSampleBuffer,
        of outputType: SCStreamOutputType
    ) {
        guard let writer = assetWriter else { return }

        if !sessionStarted, writer.status == .unknown {
            writer.startWriting()
            writer.startSession(atSourceTime: CMSampleBufferGetPresentationTimeStamp(sampleBuffer))
            sessionStarted = true
        }

        guard sessionStarted, writer.status == .writing else { return }

        switch outputType {
        case .screen:
            if videoInput?.isReadyForMoreMediaData == true {
                videoInput?.append(sampleBuffer)
            }
        case .audio:
            if audioInput?.isReadyForMoreMediaData == true {
                audioInput?.append(sampleBuffer)
            }
        @unknown default:
            break
        }
    }

    // MARK: - SCStreamDelegate

    func stream(_ stream: SCStream, didStopWithError error: Error) {
        print("[ScreenRecorder] stream stopped with error: \(error)")
    }

    // MARK: - Stop

    func stop() async throws -> (url: URL, duration: Int) {
        let elapsed = Int(Date().timeIntervalSince(startDate ?? Date()))

        guard let captureStream = self.stream else {
            throw RecorderError.serverRejected("No active recording")
        }

        try await captureStream.stopCapture()
        self.stream = nil

        return try await withCheckedThrowingContinuation { continuation in
            writerQueue.async {
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
