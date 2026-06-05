import 'package:flutter/material.dart';

import '../config/remote_config.dart';
import '../core/theme/design_tokens.dart';
import '../services/speed_test_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';

/// Full internet speed test — synced with portal `/portal/speed-test` (speedtest.sg CORS).
class CustomerSpeedTestScreen extends StatefulWidget {
  const CustomerSpeedTestScreen({super.key, this.active = false});

  final bool active;

  @override
  State<CustomerSpeedTestScreen> createState() => _CustomerSpeedTestScreenState();
}

class _CustomerSpeedTestScreenState extends State<CustomerSpeedTestScreen> {
  SpeedTestService? _service;
  SpeedTestPhase _phase = SpeedTestPhase.ready;
  bool _running = false;
  String _status = 'Ready to start.';
  double _gaugeMbps = 0;
  double? _pingMs;
  double? _downloadMbps;
  double? _uploadMbps;
  bool _cancelled = false;

  @override
  void initState() {
    super.initState();
    _initService();
  }

  void _initService() {
    _service?.dispose();
    _service = SpeedTestService(
      pingUrl: RemoteConfig.speedTestPingUrl,
      downloadUrl: RemoteConfig.speedTestDownloadUrl,
      uploadUrl: RemoteConfig.speedTestUploadUrl,
    );
  }

  @override
  void dispose() {
    _cancelled = true;
    _service?.dispose();
    super.dispose();
  }

  String _fmtMbps(double? v) {
    if (v == null) return '—';
    return v >= 100 ? v.toStringAsFixed(0) : v.toStringAsFixed(1);
  }

  Future<void> _start() async {
    if (_running || !RemoteConfig.speedTestEnabled) return;
    _initService();
    final svc = _service!;

    setState(() {
      _running = true;
      _cancelled = false;
      _phase = SpeedTestPhase.ping;
      _gaugeMbps = 0;
      _pingMs = null;
      _downloadMbps = null;
      _uploadMbps = null;
      _status = 'Measuring latency…';
    });

    try {
      final ping = await svc.measurePing(
        onSample: (ms) {
          if (!mounted || _cancelled) return;
          setState(() => _pingMs = ms);
        },
        isCancelled: () => _cancelled,
      );

      if (_cancelled || !mounted) return;
      setState(() {
        _pingMs = ping;
        _phase = SpeedTestPhase.download;
        _status = 'Testing download…';
      });

      final down = await svc.measureDownload(
        onProgress: (mbps) {
          if (!mounted || _cancelled) return;
          setState(() => _gaugeMbps = mbps);
        },
        isCancelled: () => _cancelled,
      );

      if (_cancelled || !mounted) return;
      setState(() {
        _downloadMbps = down;
        _gaugeMbps = down;
        _phase = SpeedTestPhase.upload;
        _status = 'Testing upload…';
      });

      final up = await svc.measureUpload(
        onProgress: (mbps) {
          if (!mounted || _cancelled) return;
          setState(() => _gaugeMbps = mbps);
        },
        isCancelled: () => _cancelled,
      );

      if (_cancelled || !mounted) return;
      setState(() {
        _uploadMbps = up;
        _gaugeMbps = up;
        _phase = SpeedTestPhase.done;
        _status = 'Test complete.';
        _running = false;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _status = 'Speed test failed. Check internet connection and try again.';
          _phase = SpeedTestPhase.ready;
          _running = false;
        });
      }
    }
  }

  String get _phaseLabel {
    return switch (_phase) {
      SpeedTestPhase.ready => 'READY',
      SpeedTestPhase.ping => 'PING',
      SpeedTestPhase.download => 'DOWNLOAD',
      SpeedTestPhase.upload => 'UPLOAD',
      SpeedTestPhase.done => 'DONE',
    };
  }

  Color get _gaugeColor {
    return switch (_phase) {
      SpeedTestPhase.ping => const Color(0xFF0EA5E9),
      SpeedTestPhase.download => DesignTokens.primary,
      SpeedTestPhase.upload => const Color(0xFF7C3AED),
      SpeedTestPhase.done => AppTheme.success,
      SpeedTestPhase.ready => const Color(0xFF64748B),
    };
  }

  @override
  Widget build(BuildContext context) {
    if (!RemoteConfig.speedTestEnabled) {
      return ListView(
        padding: pagePadding(context),
        children: [
          const SizedBox(height: 24),
          Icon(Icons.speed, size: 56, color: Colors.grey.shade400),
          const SizedBox(height: 12),
          const Text('Speed test is disabled on this server.', textAlign: TextAlign.center),
        ],
      );
    }

    return ListView(
      padding: pagePadding(context),
      children: [
        const SizedBox(height: 8),
        Text(
          'Internet speed test',
          style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 4),
        Text(
          'Measures your connection to the test server — same as customer portal.',
          style: TextStyle(fontSize: 13, color: Colors.grey.shade600, height: 1.35),
        ),
        const SizedBox(height: 20),
        Container(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [DesignTokens.primary.withValues(alpha: 0.08), Colors.white],
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
            ),
            borderRadius: BorderRadius.circular(DesignTokens.radiusLg),
            border: Border.all(color: DesignTokens.primary.withValues(alpha: 0.15)),
          ),
          child: Column(
            children: [
              Container(
                width: 168,
                height: 168,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: _gaugeColor, width: 5),
                  color: Colors.white,
                ),
                alignment: Alignment.center,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      _phaseLabel,
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: _gaugeColor, letterSpacing: 1),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _fmtMbps(_running || _phase == SpeedTestPhase.done ? _gaugeMbps : 0),
                      style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w900, height: 1),
                    ),
                    Text('Mbps', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _running ? null : _start,
                  style: FilledButton.styleFrom(
                    backgroundColor: DesignTokens.primary,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _running
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                        )
                      : const Text('START', style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: 1.2)),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(child: _tile('↓ Download', _fmtMbps(_downloadMbps), 'Mbps', DesignTokens.primary)),
            const SizedBox(width: 8),
            Expanded(child: _tile('↑ Upload', _fmtMbps(_uploadMbps), 'Mbps', const Color(0xFF7C3AED))),
            const SizedBox(width: 8),
            Expanded(child: _tile('◎ Latency', _pingMs?.toStringAsFixed(0) ?? '—', 'ms', const Color(0xFF0EA5E9))),
          ],
        ),
        const SizedBox(height: 12),
        Text(_status, textAlign: TextAlign.center, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
        const SizedBox(height: 16),
        Text(
          'Live Mbps from your ISP line is on Home → Full usage details.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
        ),
      ],
    );
  }

  Widget _tile(String label, String value, String unit, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: color)),
          const SizedBox(height: 6),
          RichText(
            text: TextSpan(
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
              children: [
                TextSpan(text: value),
                TextSpan(text: ' $unit', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.grey.shade600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
