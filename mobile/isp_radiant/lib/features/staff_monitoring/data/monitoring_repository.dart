import '../../../core/network/api_result.dart';
import '../../../services/api_service.dart';

double? _dN(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}');
int _i(dynamic v) => v is num ? v.toInt() : int.tryParse('${v ?? ''}') ?? 0;
String _s(dynamic v, [String f = '']) {
  final s = v?.toString();
  return (s == null || s.isEmpty) ? f : s;
}

class OnlineClient {
  const OnlineClient({
    required this.id,
    required this.name,
    required this.customerCode,
    required this.package,
    required this.sessionStarted,
    required this.onlineDuration,
    required this.downloadHuman,
    required this.uploadHuman,
  });

  final int id;
  final String name;
  final String customerCode;
  final String package;
  final String sessionStarted;
  final String onlineDuration;
  final String downloadHuman;
  final String uploadHuman;

  factory OnlineClient.fromJson(Map<String, dynamic> j) => OnlineClient(
        id: _i(j['id']),
        name: _s(j['name'], 'Client'),
        customerCode: _s(j['customer_code']),
        package: _s(j['package']),
        sessionStarted: _s(j['session_started']),
        onlineDuration: _s(j['online_duration']),
        downloadHuman: _s(j['download_human']),
        uploadHuman: _s(j['upload_human']),
      );
}

class OnlineClientsPage {
  const OnlineClientsPage({required this.totalOnline, required this.clients});
  final int totalOnline;
  final List<OnlineClient> clients;
}

class MonitoringLive {
  const MonitoringLive({
    required this.onlineCount,
    required this.bandwidthHuman,
    required this.downloadHuman,
    required this.uploadHuman,
    required this.chart,
  });

  final int? onlineCount;
  final String? bandwidthHuman;
  final String? downloadHuman;
  final String? uploadHuman;
  final Map<String, dynamic>? chart;

  factory MonitoringLive.fromJson(Map<String, dynamic> j) => MonitoringLive(
        onlineCount: _dN(j['online_count'])?.toInt(),
        bandwidthHuman: j['bandwidth_human']?.toString(),
        downloadHuman: j['download_human']?.toString(),
        uploadHuman: j['upload_human']?.toString(),
        chart: j['chart'] as Map<String, dynamic>?,
      );
}

/// Repository for staff live monitoring. Wraps the unchanged [ApiService]
/// endpoints and returns typed models / [Result].
class MonitoringRepository {
  MonitoringRepository(this._api);
  final ApiService _api;

  Future<Result<OnlineClientsPage>> onlineClients() => guard(() async {
        final body = await _api.staffOnlineClients();
        final clients = (body['data'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((e) => OnlineClient.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        return OnlineClientsPage(
          totalOnline: (body['total_online'] as num?)?.toInt() ?? clients.length,
          clients: clients,
        );
      });

  /// Live snapshot — returns null on transient failure (1s poll, stay quiet).
  Future<MonitoringLive?> live() async {
    try {
      return MonitoringLive.fromJson(await _api.staffMonitoringLive());
    } catch (_) {
      return null;
    }
  }
}
