/// Normalize API base URL for MFS Verify APK (device ingest only).
class ApiUrlNormalizer {
  /// Correct base ends with `/api/v1` — ingest path is appended in [MfsApiService].
  static String normalize(String raw) {
    var s = raw.trim();
    if (s.isEmpty) return s;

    if (!s.contains('://')) {
      s = 'https://$s';
    }

    final uri = Uri.tryParse(s);
    if (uri == null || uri.host.isEmpty) {
      return s;
    }

    var path = uri.path;
    const suffixes = [
      '/staff/mfs/sms/ingest',
      '/mfs/sms/ingest',
      '/staff/mfs/sms',
    ];
    for (final suffix in suffixes) {
      if (path.endsWith(suffix)) {
        path = path.substring(0, path.length - suffix.length);
      }
    }

    if (!path.endsWith('/api/v1')) {
      if (path.endsWith('/api')) {
        path = '$path/v1';
      } else if (!path.contains('/api/v1')) {
        path = path.endsWith('/') ? '${path}api/v1' : '$path/api/v1';
      }
    }

    path = path.replaceAll(RegExp(r'/+'), '/');
    if (path.length > 1 && path.endsWith('/')) {
      path = path.substring(0, path.length - 1);
    }

    return Uri(
      scheme: uri.scheme,
      host: uri.host,
      port: uri.hasPort ? uri.port : null,
      path: path.isEmpty ? '/api/v1' : path,
    ).toString();
  }

  static String ingestUrl(String base) => '${normalize(base)}/mfs/sms/ingest';

  static bool looksValid(String raw) {
    final n = normalize(raw);
    final u = Uri.tryParse(n);
    return u != null && u.host.isNotEmpty && n.contains('/api/v1');
  }
}
