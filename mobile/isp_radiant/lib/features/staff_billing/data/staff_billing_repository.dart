import '../../../core/network/api_result.dart';
import '../../../services/api_service.dart';
import '../domain/billing_models.dart';

/// Aggregated billing snapshot loaded for the Billing hub.
class BillingBundle {
  const BillingBundle({
    required this.summary,
    required this.due,
    required this.invoices,
    required this.collections,
    required this.collectionSummary,
  });

  final BillingSummary summary;
  final List<DueClient> due;
  final List<InvoiceRow> invoices;
  final List<CollectionRecord> collections;
  final CollectionSummary collectionSummary;
}

/// Repository for staff billing. Wraps the unchanged [ApiService] endpoints and
/// returns typed models / [Result].
class StaffBillingRepository {
  StaffBillingRepository(this._api);
  final ApiService _api;

  List<Map<String, dynamic>> _rows(Map<String, dynamic> body) =>
      (body['data'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();

  Future<Result<BillingBundle>> loadAll({String invoiceStatus = 'all'}) => guard(() async {
        final summary = await _api.staffBillingSummary();
        final dueBody = await _api.staffBillingDue();
        final invBody = await _api.staffBillingInvoices(status: invoiceStatus);
        final colBody = await _api.staffBillingCollections();

        final collections = _rows(colBody).map(CollectionRecord.fromJson).toList();
        return BillingBundle(
          summary: BillingSummary.fromJson(summary['billing'] as Map<String, dynamic>? ?? const {}),
          due: _rows(dueBody).map(DueClient.fromJson).toList(),
          invoices: _rows(invBody).map(InvoiceRow.fromJson).toList(),
          collections: collections,
          collectionSummary: CollectionSummary.fromJson(
            colBody['summary'] as Map<String, dynamic>? ?? const {},
            collections.length,
          ),
        );
      });

  Future<Result<List<InvoiceRow>>> invoices(String status) => guard(() async {
        final body = await _api.staffBillingInvoices(status: status);
        return _rows(body).map(InvoiceRow.fromJson).toList();
      });

  // Quick actions reused by the due-client card.
  Future<Result<Map<String, dynamic>>> customerDetail(int id) =>
      guard(() => _api.staffCustomerDetail(id));
  Future<Result<void>> toggleNetwork(int id) => guard(() => _api.staffToggleNetwork(id));
  Future<Result<void>> extendService(int id) => guard(() => _api.staffExtendService(id));
  Future<Result<void>> smsReminder(int id) => guard(() => _api.staffSmsReminder(id));
}
