<?php

namespace App\Actions\Auth;

use App\Models\Player;
use App\Services\Player\PlayerBootstrapService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LobbyLoginAction
{
    public function __construct(
        private readonly PlayerBootstrapService $bootstrapService,
    ) {}

    public function execute(string $token, string $clientDetails): Player
    {
        $serverId = filter_var(config('thehools.world.server_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        abort_if($serverId === false, 503, 'World identity is not configured');

        $row = DB::connection('lobby')
            ->table('accounts_servers')
            ->where('hash', $token)
            ->where('server_id', $serverId)
            ->where('last_login', '>', now()->subSeconds(20))
            ->first();

        if ($row === null) {
            abort(403, 'Token expired or invalid');
        }

        $player = Player::firstOrCreate(
            ['lobby_account_id' => $row->account_id],
            ['nickname' => $row->uzyt_nazwa],
        );

        if ($player->nickname !== $row->uzyt_nazwa && $row->uzyt_nazwa !== '123') {
            $player->update(['nickname' => $row->uzyt_nazwa]);
        }

        DB::connection('lobby')
            ->table('accounts_servers')
            ->where('hash', $token)
            ->where('server_id', $serverId)
            ->update([
                'hash' => null,
                'client_details' => null,
            ]);

        $player = $this->bootstrapService->ensurePlayerState($player);

        Auth::login($player);
        session()->regenerate();

        return $player;
    }
}
