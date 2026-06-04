import '../../../core/network/api_result.dart';
import '../../../services/api_service.dart';
import '../domain/customer_models.dart';

/// Repository for the customer billing / services area. Wraps [ApiService] and
/// returns typed models / [Result] — no raw maps leak into the UI. All
/// endpoints are unchanged.
class CustomerRepository {
  CustomerRepository(this._api);
  final ApiService _api;

  Future<Result<List<PaymentRecord>>> payments({int page = 1}) => guard(() async {
        final list = await _api.customerPayments(page: page);
        return list.map(PaymentRecord.fromJson).toList();
      });

  Future<Result<Payables>> payables() =>
      guard(() async => Payables.fromJson(await _api.customerPayables()));

  Future<Result<InvoiceDetail>> invoiceDetail(int id) =>
      guard(() async => InvoiceDetail.fromJson(await _api.customerBillDetail(id)));

  Future<Result<List<PackageOption>>> packages() => guard(() async {
        final list = await _api.customerPackages();
        return list.map(PackageOption.fromJson).toList();
      });

  Future<Result<OnuStatus>> onuStatus() => guard(() async {
        final body = await _api.customerOnuStatus();
        return OnuStatus.fromJson(body['onu'] as Map<String, dynamic>? ?? const {});
      });

  /// Support tickets stay as raw maps because the shared ticket card widget
  /// consumes a map; still routed through the repository for consistency.
  Future<Result<List<Map<String, dynamic>>>> tickets() =>
      guard(() => _api.customerTickets());

  // --- commands (return the server message / payment url to the caller) ---

  Future<Result<Map<String, dynamic>>> payInvoice(int invoiceId, {required String gateway}) =>
      guard(() => _api.initiateBillPayment(invoiceId, gateway: gateway));

  Future<Result<Map<String, dynamic>>> payPrepay({required int months, required String gateway}) =>
      guard(() => _api.initiatePrepayPayment(months: months, gateway: gateway));

  Future<Result<String>> requestPackageChange(int packageId) => guard(() async {
        final res = await _api.requestPackageChange(packageId);
        return res['message']?.toString() ?? 'Request sent';
      });

  Future<Result<String>> rebootOnu() => guard(() async {
        final res = await _api.customerOnuReboot();
        return res['message']?.toString() ?? 'Reboot requested';
      });

  Future<Result<String>> createTicket({required String subject, required String description}) =>
      guard(() async {
        await _api.createTicket(subject: subject, description: description);
        return 'Ticket submitted successfully';
      });
}
