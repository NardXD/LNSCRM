import SwiftUI

// MARK: - Root

struct ContentView: View {
    @StateObject private var viewModel = RecorderViewModel()

    var body: some View {
        Group {
            if viewModel.isAuthenticated {
                MainRecorderView(viewModel: viewModel)
            } else {
                LoginView(viewModel: viewModel)
            }
        }
    }
}

// MARK: - Login

struct LoginView: View {
    @ObservedObject var viewModel: RecorderViewModel

    @State private var serverUrl = ""
    @State private var subdomain = ""
    @State private var email = ""
    @State private var password = ""
    @State private var isLoading = false

    var body: some View {
        VStack(spacing: 0) {
            Text("ItsWorkPlace Recorder")
                .font(.title2)
                .fontWeight(.semibold)
                .padding(.top, 28)
                .padding(.bottom, 20)

            Form {
                Section("Server") {
                    TextField("Server URL", text: $serverUrl)
                        .autocorrectionDisabled()
                }
                Section("Company") {
                    TextField("Subdomain", text: $subdomain)
                        .autocorrectionDisabled()
                }
                Section("Credentials") {
                    TextField("Email", text: $email)
                        .autocorrectionDisabled()
                    SecureField("Password", text: $password)
                }
            }
            .formStyle(.grouped)

            if let error = viewModel.errorMessage {
                Text(error)
                    .foregroundColor(.red)
                    .font(.caption)
                    .padding(.horizontal)
                    .padding(.top, 4)
            }

            Button(action: handleLogin) {
                HStack {
                    if isLoading { ProgressView().scaleEffect(0.7) }
                    Text("Sign In").fontWeight(.semibold)
                }
                .frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .controlSize(.large)
            .disabled(serverUrl.isEmpty || subdomain.isEmpty || email.isEmpty || password.isEmpty || isLoading)
            .padding()
        }
        .frame(width: 420)
        .onAppear { serverUrl = viewModel.savedServerUrl }
    }

    private func handleLogin() {
        isLoading = true
        Task {
            await viewModel.login(
                email: email,
                password: password,
                subdomain: subdomain,
                serverUrl: serverUrl
            )
            isLoading = false
        }
    }
}

// MARK: - Main recorder view

struct MainRecorderView: View {
    @ObservedObject var viewModel: RecorderViewModel

    var body: some View {
        NavigationStack {
            List {
                timeTrackingSection
                recordingSection
                syncSection
                if !viewModel.todaysRecordings.isEmpty {
                    todaysRecordingsSection
                }
                if !viewModel.statusMessage.isEmpty {
                    statusSection
                }
            }
            .navigationTitle("Recorder")
            .toolbar {
                ToolbarItem(placement: .primaryAction) {
                    Button("Logout") { viewModel.logout() }
                }
                ToolbarItem(placement: .automatic) {
                    Button {
                        Task { await viewModel.refreshStatus() }
                    } label: {
                        Image(systemName: "arrow.clockwise")
                    }
                }
            }
        }
        .alert("Error", isPresented: .init(
            get: { viewModel.errorMessage != nil },
            set: { if !$0 { viewModel.errorMessage = nil } }
        )) {
            Button("OK", role: .cancel) { viewModel.errorMessage = nil }
        } message: {
            Text(viewModel.errorMessage ?? "")
        }
        .task { await viewModel.refreshStatus() }
    }

    // MARK: Sections

    private var timeTrackingSection: some View {
        Section("Time Tracking") {
            HStack(spacing: 12) {
                Image(systemName: viewModel.isClockedIn ? "clock.fill" : "clock")
                    .foregroundColor(viewModel.isClockedIn ? .green : .secondary)
                    .frame(width: 24)
                Text(viewModel.isClockedIn ? "Clocked In" : "Not Clocked In")
                Spacer()
                Button(viewModel.isClockedIn ? "Clock Out" : "Clock In") {
                    Task {
                        if viewModel.isClockedIn { await viewModel.clockOut() }
                        else { await viewModel.clockIn() }
                    }
                }
                .buttonStyle(.bordered)
                .tint(viewModel.isClockedIn ? .red : .green)
            }
            .padding(.vertical, 4)
        }
    }

    private var recordingSection: some View {
        Section("Screen Recording") {
            // Auto-capture row — visible while clocked in
            if viewModel.isClockedIn {
                HStack(spacing: 12) {
                    Image(systemName: "clock.arrow.circlepath")
                        .foregroundColor(viewModel.isAutoCapturing ? .orange : .blue)
                        .frame(width: 24)
                    VStack(alignment: .leading, spacing: 2) {
                        Text("Automatic Capture")
                        if viewModel.isAutoCapturing {
                            Text("Capturing now…")
                                .font(.caption)
                                .foregroundColor(.orange)
                        } else if viewModel.nextCaptureIn > 0 {
                            Text("Next in \(formatCountdown(viewModel.nextCaptureIn))")
                                .font(.caption)
                                .foregroundColor(.secondary)
                                .monospacedDigit()
                        }
                    }
                    Spacer()
                }
                .padding(.vertical, 2)
            }

            // Manual recording row
            HStack(spacing: 12) {
                Image(systemName: viewModel.isRecording ? "record.circle.fill" : "record.circle")
                    .foregroundColor(viewModel.isRecording ? .red : .secondary)
                    .frame(width: 24)
                VStack(alignment: .leading, spacing: 2) {
                    Text(viewModel.isAutoCapturing ? "Auto Recording" : (viewModel.isRecording ? "Recording" : "Manual Recording"))
                    if viewModel.isRecording {
                        Text(formatDuration(viewModel.recordingDuration))
                            .font(.caption)
                            .foregroundColor(.secondary)
                            .monospacedDigit()
                    }
                }
                Spacer()
                if !viewModel.isAutoCapturing {
                    Button(viewModel.isRecording ? "Stop" : "Start") {
                        Task {
                            if viewModel.isRecording { await viewModel.stopRecording() }
                            else { await viewModel.startRecording() }
                        }
                    }
                    .buttonStyle(.bordered)
                    .tint(viewModel.isRecording ? .red : .blue)
                }
            }
            .padding(.vertical, 4)
        }
    }

    private var syncSection: some View {
        Section("Upload Queue") {
            HStack(spacing: 12) {
                Image(systemName: "icloud.and.arrow.up")
                    .foregroundColor(viewModel.pendingUploads > 0 ? .orange : .secondary)
                    .frame(width: 24)
                Text(viewModel.pendingUploads > 0
                     ? "\(viewModel.pendingUploads) file(s) pending"
                     : "Queue empty")
                Spacer()
                Button("Sync Now") {
                    Task { await viewModel.syncUploads() }
                }
                .buttonStyle(.bordered)
                .disabled(viewModel.pendingUploads == 0)
            }
            .padding(.vertical, 4)
        }
    }

    private var todaysRecordingsSection: some View {
        Section("Today's Recordings") {
            ForEach(viewModel.todaysRecordings) { rec in
                HStack {
                    VStack(alignment: .leading, spacing: 2) {
                        Text(rec.date).font(.subheadline)
                        if let dur = rec.screenRecordingDuration {
                            Text(formatDuration(dur))
                                .font(.caption)
                                .foregroundColor(.secondary)
                                .monospacedDigit()
                        }
                    }
                    Spacer()
                    Text(rec.syncStatus)
                        .font(.caption)
                        .foregroundColor(rec.syncStatus == "synced" ? .green : .orange)
                }
            }
        }
    }

    private var statusSection: some View {
        Section {
            Text(viewModel.statusMessage)
                .font(.caption)
                .foregroundColor(.secondary)
        }
    }

    // MARK: Helpers

    private func formatDuration(_ seconds: Int) -> String {
        let h = seconds / 3600
        let m = (seconds % 3600) / 60
        let s = seconds % 60
        return String(format: "%02d:%02d:%02d", h, m, s)
    }

    private func formatCountdown(_ seconds: Int) -> String {
        let m = seconds / 60
        let s = seconds % 60
        return String(format: "%d:%02d", m, s)
    }
}
