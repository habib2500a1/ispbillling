import 'dart:async';
import 'dart:math';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

/// Internet speed test — same external CORS endpoints as portal (`portal-speedtest-live.js`).
class SpeedTestService {
  SpeedTestService({
    required this.pingUrl,
    required this.downloadUrl,
    required this.uploadUrl,
    http.Client? client,
  }) : _client = client ?? http.Client();

  final String pingUrl;
  final String downloadUrl;
  final String uploadUrl;
  final http.Client _client;

  static const _pingSamples = 6;
  static const _downloadMs = 9000;
  static const _downloadStreams = 6;
  static const _downloadBytesPerReq = 50 * 1000 * 1000;
  static const _uploadMs = 8000;
  static const _uploadStreams = 3;
  static const _uploadBlobBytes = 1 * 1000 * 1000;

  String _cacheBust(String url) {
    final sep = url.contains('?') ? '&' : '?';
    return '$url${sep}_=${DateTime.now().millisecondsSinceEpoch}${Random().nextInt(9999)}';
  }

  Future<double?> measurePing({
    void Function(double? ms)? onSample,
    bool Function()? isCancelled,
  }) async {
    var best = double.infinity;

    try {
      await _client.get(Uri.parse(_cacheBust(pingUrl))).timeout(const Duration(seconds: 8));
    } catch (_) {}

    for (var i = 0; i < _pingSamples; i++) {
      if (isCancelled?.call() == true) return null;
      final t0 = DateTime.now();
      try {
        await _client.get(Uri.parse(_cacheBust(pingUrl))).timeout(const Duration(seconds: 8));
        final ms = DateTime.now().difference(t0).inMilliseconds.toDouble();
        if (ms < best) best = ms;
        onSample?.call(best);
      } catch (_) {}
    }

    return best.isFinite ? best : null;
  }

  Future<double> measureDownload({
    void Function(double mbps)? onProgress,
    bool Function()? isCancelled,
  }) async {
    final stopAt = DateTime.now().add(const Duration(milliseconds: _downloadMs));
    var bytes = 0;
    final t0 = DateTime.now();
    final futures = <Future<void>>[];

    for (var i = 0; i < _downloadStreams; i++) {
      futures.add(_downloadLoop(stopAt, () => isCancelled?.call() == true, (n) {
        bytes += n;
        final sec = DateTime.now().difference(t0).inMilliseconds / 1000.0;
        if (sec > 0.25) onProgress?.call((bytes * 8) / sec / 1e6);
      }));
    }

    await Future.wait(futures);
    final sec = max(DateTime.now().difference(t0).inMilliseconds / 1000.0, 0.001);
    return (bytes * 8) / sec / 1e6;
  }

  Future<void> _downloadLoop(
    DateTime stopAt,
    bool Function() cancelled,
    void Function(int bytes) onBytes,
  ) async {
    while (DateTime.now().isBefore(stopAt) && !cancelled()) {
      try {
        final base = _cacheBust(downloadUrl);
        final sep = base.contains('?') ? '&' : '?';
        final url = '$base${sep}bytes=$_downloadBytesPerReq';
        final req = http.Request('GET', Uri.parse(url));
        final res = await _client.send(req).timeout(const Duration(seconds: 30));
        await for (final chunk in res.stream) {
          if (cancelled() || DateTime.now().isAfter(stopAt)) {
            await res.stream.drain();
            return;
          }
          onBytes(chunk.length);
        }
      } catch (_) {
        await Future<void>.delayed(const Duration(milliseconds: 150));
      }
    }
  }

  Future<double> measureUpload({
    void Function(double mbps)? onProgress,
    bool Function()? isCancelled,
  }) async {
    final blob = _buildUploadBlob();
    final stopAt = DateTime.now().add(const Duration(milliseconds: _uploadMs));
    var bytes = 0;
    final t0 = DateTime.now();
    final futures = <Future<void>>[];

    for (var i = 0; i < _uploadStreams; i++) {
      futures.add(_uploadLoop(blob, stopAt, () => isCancelled?.call() == true, (n) {
        bytes += n;
        final sec = DateTime.now().difference(t0).inMilliseconds / 1000.0;
        if (sec > 0.25) onProgress?.call((bytes * 8) / sec / 1e6);
      }));
    }

    await Future.wait(futures);
    final sec = max(DateTime.now().difference(t0).inMilliseconds / 1000.0, 0.001);
    return (bytes * 8) / sec / 1e6;
  }

  Future<void> _uploadLoop(
    Uint8List blob,
    DateTime stopAt,
    bool Function() cancelled,
    void Function(int bytes) onBytes,
  ) async {
    while (DateTime.now().isBefore(stopAt) && !cancelled()) {
      try {
        final req = http.StreamedRequest('POST', Uri.parse(_cacheBust(uploadUrl)));
        req.headers['Content-Type'] = 'application/octet-stream';
        const chunkSize = 64 * 1024;
        var sent = 0;
        while (sent < blob.length && !cancelled()) {
          final end = min(sent + chunkSize, blob.length);
          req.sink.add(blob.sublist(sent, end));
          final delta = end - sent;
          sent = end;
          onBytes(delta);
        }
        await req.sink.close();
        await _client.send(req).timeout(const Duration(seconds: 30));
      } catch (_) {
        await Future<void>.delayed(const Duration(milliseconds: 150));
      }
    }
  }

  Uint8List _buildUploadBlob() {
    final arr = Uint8List(_uploadBlobBytes);
    final rnd = Random();
    for (var i = 0; i < arr.length; i += 4096) {
      arr[i] = rnd.nextInt(256);
    }
    return arr;
  }

  void dispose() => _client.close();
}

enum SpeedTestPhase { ready, ping, download, upload, done }
