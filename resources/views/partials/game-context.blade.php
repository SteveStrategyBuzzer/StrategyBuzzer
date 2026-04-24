{{--
    SB_GAME_CONTEXT — Single source of truth for gameplay window vars.

    Usage in any view extending layouts.game:
        @include('partials.game-context', [
            'roomId'         => $room_id ?? null,
            'lobbyCode'      => $lobby_code ?? null,
            'jwtToken'       => $jwt_token ?? null,
            'matchId'        => $match_id ?? null,
            'mode'           => 'duo',
            'page'           => 'question',          // lobby|intro|question|answer|result|round-scoreboard|waiting|resume
            'totalQuestions' => $totalQuestions ?? 10,
            'playerName'     => $playerName ?? null, // REQUIRED for socket join_room (server schema)
            'playerInfo'     => ['avatarId' => $playerAvatar ?? null],
            'noBrainOverlay' => true,
            'noSocketOverlay'=> false,
            'hideHeader'     => false,
        ])

    The partial publishes:
      - window.SB_GAME_CONTEXT (canonical, modern)
      - Legacy window.* vars (ROOM_ID, JWT_TOKEN, etc.) for back-compat with existing scripts
--}}
<script>
(function () {
    var ctx = {
        roomId:         @json((string)($roomId ?? '')),
        lobbyCode:      @json($lobbyCode ?? null),
        jwtToken:       @json((string)($jwtToken ?? '')),
        matchId:        @json((string)($matchId ?? '')),
        currentUserId:  @json((string)(auth()->id() ?? '')),
        playerName:     @json((string)($playerName ?? (auth()->user()->name ?? 'Joueur'))),
        playerInfo:     @json($playerInfo ?? new \stdClass()),
        mode:           @json($mode ?? 'duo'),
        page:           @json($page ?? ''),
        totalQuestions: {{ (int)($totalQuestions ?? 10) }},
        gameServerUrl:  window.GAME_SERVER_URL || null
    };
    window.SB_GAME_CONTEXT = ctx;

    // Legacy window globals (back-compat — existing scripts continue to work)
    window.ROOM_ID         = ctx.roomId         || null;
    window.LOBBY_CODE      = ctx.lobbyCode      || null;
    window.JWT_TOKEN       = ctx.jwtToken       || null;
    window.MATCH_ID        = ctx.matchId        || '';
    window.CURRENT_USER_ID = ctx.currentUserId  || '';
    window.PLAYER_NAME     = ctx.playerName     || null;
    window.PLAYER_INFO     = ctx.playerInfo     || {};
    window.TOTAL_QUESTIONS = ctx.totalQuestions || 10;
    window.CURRENT_PAGE    = ctx.page           || '';

    @if(!empty($noBrainOverlay))   window.NO_BRAIN_OVERLAY  = true; @endif
    @if(!empty($noSocketOverlay))  window.NO_SOCKET_OVERLAY = true; @endif
    @if(!empty($hideHeader))       window.GR_HIDE_HEADER    = true; @endif

    @isset($extraWindowVars)
        @foreach($extraWindowVars as $k => $v)
            window.{!! $k !!} = @json($v);
        @endforeach
    @endisset
})();
</script>
