import '../../../core/network/api_result.dart';
import '../../../services/api_service.dart';
import '../domain/customer_list_item.dart';

/// One page of the customer list plus paging metadata.
class CustomerPage {
  const CustomerPage({required this.items, required this.page, required this.lastPage, required this.total});
  final List<CustomerListItem> items;
  final int page;
  final int lastPage;
  final int total;
  bool get hasMore => page < lastPage;
}

/// Repository for staff customer browsing + quick actions. Wraps the unchanged
/// [ApiService] endpoints and returns typed models / [Result].
class StaffCustomersRepository {
  StaffCustomersRepository(this._api);
  final ApiService _api;

  Future<Result<CustomerPage>> list({int page = 1, String? status, bool dueOnly = false}) =>
      guard(() async {
        final body = await _api.staffCustomers(
          page: page,
          status: (status == null || status.isEmpty) ? null : status,
          dueOnly: dueOnly,
        );
        final items = (body['data'] as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((e) => CustomerListItem.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        final meta = body['meta'] as Map<String, dynamic>? ?? const {};
        return CustomerPage(
          items: items,
          page: page,
          lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
          total: (meta['total'] as num?)?.toInt() ?? items.length,
        );
      });

  Future<Result<List<CustomerListItem>>> search(String query) => guard(() async {
        final list = await _api.searchCustomers(query);
        return list.map(CustomerListItem.fromJson).toList();
      });

  Future<Result<void>> toggleNetwork(int customerId) =>
      guard(() => _api.staffToggleNetwork(customerId));

  Future<Result<void>> smsReminder(int customerId) =>
      guard(() => _api.staffSmsReminder(customerId));

  /// Full detail map (kept raw — consumed by the detail/edit screens for now).
  Future<Result<Map<String, dynamic>>> detail(int customerId) =>
      guard(() => _api.staffCustomerDetail(customerId));
}
