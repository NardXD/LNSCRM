import SwiftUI

@main
struct RecorderApp: App {
    var body: some Scene {
        WindowGroup {
            ContentView()
                .frame(minWidth: 480, minHeight: 540)
        }
        .windowResizability(.contentMinSize)
    }
}
