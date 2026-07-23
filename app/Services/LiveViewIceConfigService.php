<?php

namespace App\Services;

class LiveViewIceConfigService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function iceServers(): array
    {
        $servers = [];

        foreach (config('live-view.stun_urls', []) as $url) {
            $servers[] = ['urls' => $url];
        }

        $turnUrls = config('live-view.turn_urls', []);
        $turnUsername = config('live-view.turn_username');
        $turnCredential = config('live-view.turn_credential');

        if ($turnUrls !== [] && $turnUsername && $turnCredential) {
            $servers[] = [
                'urls' => $turnUrls,
                'username' => $turnUsername,
                'credential' => $turnCredential,
            ];
        }

        if ($servers === []) {
            $servers[] = ['urls' => 'stun:stun.l.google.com:19302'];
        }

        return $servers;
    }

    public function turnConfigured(): bool
    {
        return config('live-view.turn_urls') !== []
            && config('live-view.turn_username')
            && config('live-view.turn_credential');
    }
}
