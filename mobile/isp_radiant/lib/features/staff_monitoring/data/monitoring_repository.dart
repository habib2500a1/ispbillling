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

class ClientMonitorStats {
  const ClientMonitorStats({required this.total, required this.online, required this.offline});

  final int total;
  final int online;
  final int offline;

  factory ClientMonitorStats.fromJson(Map<String, dynamic> j) => ClientMonitorStats(
        total: _i(j['total']),
        online: _i(j['online']),
        offline: _i(j['offline']),
      );
}

class ClientMonitorRow {
  const ClientMonitorRow({
    required this.id,
    required this.name,
    required this.customerCode,
    required this.username,
    required this.phone,
    required this.zone,
    required this.subzone,
    required this.box,
    required this.profile,
    required this.framedIp,
    required this.isOnline,
    required this.connectionStatus,
    required this.lastLogout,
    required this.mikrotikServerName,
  });

  final int id;
  final String name;
  final String customerCode;
  final String username;
  final String phone;
  final String zone;
  final String subzone;
  final String box;
  final String profile;
  final String framedIp;
  final bool isOnline;
  final String connectionStatus;
  final String lastLogout;
  final String mikrotikServerName;

  factory ClientMonitorRow.fromJson(Map<String, dynamic> j) => ClientMonitorRow(
        id: _i(j['id']),
        name: _s(j['name'], 'Client'),
        customerCode: _s(j['customer_code']),
        username: _s(j['username']),
        phone: _s(j['phone']),
        zone: _s(j['zone']),
        subzone: _s(j['subzone'], 'N/A'),
        box: _s(j['box'], 'N/A'),
        profile: _s(j['profile']),
        framedIp: _s(j['framed_ip']),
        isOnline: j['is_online'] == true,
        connectionStatus: _s(j['connection_status'], 'Offline'),
        lastLogout: _s(j['last_logout']),
        mikrotikServerName: _s(j['mikrotik_server_name']),
      );
}

class ClientMonitorFilters {
  const ClientMonitorFilters({
    required this.routers,
    required this.zones,
    required this.subzones,
  });

  final List<Map<String, dynamic>> routers;
  final List<Map<String, dynamic>> zones;
  final List<Map<String, dynamic>> subzones;

  factory ClientMonitorFilters.fromJson(Map<String, dynamic> j) => ClientMonitorFilters(
        routers: (j['routers'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList(),
        zones: (j['zones'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList(),
        subzones: (j['subzones'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList(),
      );
}

class ClientMonitorPage {
  const ClientMonitorPage({
    required this.stats,
    required this.filters,
    required this.clients,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final ClientMonitorStats stats;
  final ClientMonitorFilters filters;
  final List<ClientMonitorRow> clients;
  final int currentPage;
  final int lastPage;
  final int total;
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

  Future<Result<ClientMonitorPage>> clientMonitoring({
    String q = '',
    int? mikrotikServerId,
    int? zoneId,
    int? subzoneId,
    String connection = 'all',
    int page = 1,
    int perPage = 25,
  }) =>
      guard(() async {
        final body = await _api.staffMonitoringClients(
          q: q,
          mikrotikServerId: mikrotikServerId,
          zoneId: zoneId,
          subzoneId: subzoneId,
          connection: connection,
          page: page,
          perPage: perPage,
        );
        final clients = (body['data'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((e) => ClientMonitorRow.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        final meta = body['meta'] as Map<String, dynamic>? ?? const {};
        return ClientMonitorPage(
          stats: ClientMonitorStats.fromJson(body['stats'] as Map<String, dynamic>? ?? const {}),
          filters: ClientMonitorFilters.fromJson(body['filters'] as Map<String, dynamic>? ?? const {}),
          clients: clients,
          currentPage: _i(meta['current_page']),
          lastPage: _i(meta['last_page']),
          total: _i(meta['total']),
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
