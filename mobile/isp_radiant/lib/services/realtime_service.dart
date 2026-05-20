import 'dart:async';

import 'api_service.dart';

/// Polls staff dashboard when WebSocket is disabled (Reverb not configured).
class RealtimeService {
  RealtimeService(this._api);

  final ApiService _api;
  Timer? _timer;
  void Function(Map<String, dynamic> config)? onConfig;
  void Function()? onTick;

  Future<void> start() async {
    stop();
    try {
      final cfg = await _api.realtimeConfig();
      onConfig?.call(cfg);
      final enabled = cfg['enabled'] == true;
      final seconds = (cfg['polling_fallback_seconds'] as num?)?.toInt() ?? 90;
      if (!enabled) {
        _timer = Timer.periodic(Duration(seconds: seconds), (_) => onTick?.call());
      }
    } catch (_) {
      _timer = Timer.periodic(const Duration(seconds: 90), (_) => onTick?.call());
    }
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
  }
}
