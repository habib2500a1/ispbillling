import '../../../core/network/api_result.dart';
import '../../../services/api_service.dart';

/// Repository for staff communications (bulk due SMS + notice broadcast).
/// Wraps the unchanged [ApiService] endpoints and returns [Result].
class CommsRepository {
  CommsRepository(this._api);
  final ApiService _api;

  Future<Result<String>> sendBulkDue({String? message}) => guard(() async {
        final res = await _api.staffSmsBulkDue(message: message);
        return res['message']?.toString() ?? 'Due reminders sent';
      });

  Future<Result<String>> broadcastNotice(String message, {String target = 'active'}) =>
      guard(() async {
        final res = await _api.staffBroadcastNotice(message, target: target);
        return res['message']?.toString() ?? 'Notice broadcast';
      });
}
