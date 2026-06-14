import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isp_radiant/app.dart';
import 'package:isp_radiant/screens/reseller_home_screen.dart';
import 'package:isp_radiant/screens/staff_add_customer_screen.dart';
import 'package:isp_radiant/screens/staff_expense_screen.dart';
import 'package:isp_radiant/screens/staff_monitoring_screen.dart';
import 'package:isp_radiant/screens/staff_receive_bill_screen.dart';
import 'package:isp_radiant/services/api_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

class FakeApiService extends ApiService {
  bool expenseSubmitted = false;
  bool paymentSubmitted = false;
  String? expenseSource;
  String? logoutRole;

  @override
  Future<void> loadRemoteConfig({Duration? timeout}) async {}

  @override
  Future<Map<String, dynamic>> staffCollectionOptions() async => {
        'can_pick_collector': false,
        'default_collector_id': 7,
        'collectors': [
          {'id': 7, 'name': 'Test Staff', 'label': 'Test Staff (me)'},
        ],
      };

  @override
  Future<List<Map<String, dynamic>>> staffPaymentMethods() async => [
        {'code': 'cash', 'label': 'Cash'},
        {'code': 'bkash', 'label': 'bKash'},
      ];

  @override
  Future<Map<String, dynamic>> recordCollection({
    required int customerId,
    required double amount,
    int? invoiceId,
    String method = 'cash',
    String? reference,
    String? notes,
    int? collectorUserId,
    String discountPreset = 'none',
    double? discountCustom,
  }) async {
    paymentSubmitted = collectorUserId == 7 && amount > 0;
    return {'message': 'Payment recorded'};
  }

  @override
  Future<Map<String, dynamic>> staffMonitoringClients({
    String q = '',
    int? mikrotikServerId,
    int? zoneId,
    int? subzoneId,
    int? areaId,
    String connection = 'all',
    int page = 1,
    int perPage = 25,
  }) async =>
      {
        'stats': {'total': 2, 'online': 1, 'offline': 1},
        'filters': {
          'routers': [
            {'id': 1, 'name': 'Router 1'},
          ],
          'zones': const [],
          'subzones': const [],
          'areas': const [],
        },
        'data': [
          {
            'id': 1,
            'name': 'Habibur Rahman',
            'customer_code': 'C001',
            'username': 'habibfree',
            'phone': '01841558023',
            'zone': 'Zone A',
            'profile': '24Mbps',
            'is_online': true,
            'connection_status': 'Online',
          },
        ],
        'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
      };

  @override
  Future<List<Map<String, dynamic>>> collectorExpenseCategories() async => [
        {'id': 10, 'name': 'Vendor service bill', 'expense_source': 'vendor'},
      ];

  @override
  Future<List<Map<String, dynamic>>> staffExpenses() async => [];

  @override
  Future<Map<String, dynamic>> submitCollectorExpense({
    required double amount,
    required int categoryId,
    String expenseSource = 'office',
    String? description,
    String? expenseDate,
  }) async {
    expenseSubmitted = amount == 500 && categoryId == 10;
    this.expenseSource = expenseSource;
    return {'data': {'id': 1}};
  }

  @override
  Future<Map<String, dynamic>> staffCustomerFormOptions() async => {
        'packages': [
          {'id': 1, 'name': '24Mbps', 'monthly_bill': 500},
        ],
        'mikrotik_servers': [
          {'id': 1, 'name': 'Router 1'},
        ],
        'areas': const [],
        'zones': const [],
        'subzones': const [],
        'districts': const [],
        'upazilas': const [],
        'gender_options': const [],
        'segments': const [],
        'subscriber_types': const [],
        'connection_types': const [],
        'onu_ownership_options': const [],
      };

  @override
  Future<Map<String, dynamic>> resellerDashboard() async => {
        'metrics': {
          'month_collection': 1234,
          'customers_active': 9,
          'due_amount': 456,
        },
      };

  @override
  Future<List<Map<String, dynamic>>> resellerCustomers({String? q}) async => [
        {'name': 'Client A'},
      ];

  @override
  Future<List<Map<String, dynamic>>> resellerCommissions() async => [
        {'name': 'Commission A'},
      ];

  @override
  Future<void> logout() async {
    logoutRole = 'reseller';
  }
}

Widget _wrap(Widget child) => MaterialApp(home: child);

Future<void> _phone(WidgetTester tester, Widget child) async {
  tester.view.physicalSize = const Size(1080, 2200);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(child);
}

void main() {
  testWidgets('App boots inside ProviderScope', (WidgetTester tester) async {
    SharedPreferences.setMockInitialValues({});
    await _phone(tester, const ProviderScope(child: IspRadiantApp()));
    await tester.pump(const Duration(seconds: 5));
    // Splash gate renders the brand name while booting.
    expect(find.text('Radiant'), findsWidgets);
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('Receive Bill can select staff and submit payment', (tester) async {
    final api = FakeApiService();
    await _phone(tester, _wrap(StaffReceiveBillScreen(
      api: api,
      customer: {
        'id': 1,
        'name': 'Habibur Rahman',
        'username': 'habibfree',
        'phone': '01841558023',
        'package_speed': 24,
        'monthly_bill': 500,
        'balance_due': 500,
      },
    )));
    await tester.pumpAndSettle();
    expect(find.text('Received By'), findsOneWidget);
    expect(find.text('Test Staff'), findsOneWidget);
    await tester.ensureVisible(find.text('Submit'));
    await tester.tap(find.text('Submit'));
    await tester.pumpAndSettle();
    expect(api.paymentSubmitted, isTrue);
  });

  testWidgets('Monitoring loads client stats and cards', (tester) async {
    final api = FakeApiService();
    await _phone(tester, _wrap(StaffMonitoringScreen(api: api)));
    await tester.pumpAndSettle();
    expect(find.text('Client Monitoring'), findsOneWidget);
    expect(find.textContaining('Showing Result: 1 of 2'), findsOneWidget);
    expect(find.textContaining('Online 1'), findsWidgets);
  });

  testWidgets('Expense sends category source on submit', (tester) async {
    final api = FakeApiService();
    await _phone(tester, _wrap(StaffExpenseScreen(api: api)));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).first, '500');
    await tester.tap(find.text('Submit expense'));
    await tester.pumpAndSettle();
    expect(api.expenseSubmitted, isTrue);
    expect(api.expenseSource, 'vendor');
  });

  testWidgets('Add Client exposes PPPoE fields on package step without blocked map', (tester) async {
    final api = FakeApiService();
    await _phone(tester, _wrap(StaffAddCustomerScreen(api: api)));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).at(0), 'Test Client');
    await tester.enterText(find.byType(TextField).at(1), '01800000000');
    await tester.testTextInput.receiveAction(TextInputAction.done);
    await tester.pump();
    await tester.ensureVisible(find.text('NEXT >'));
    await tester.tap(find.text('NEXT >'));
    await tester.pumpAndSettle();
    expect(find.text('PPPoE username & password'), findsOneWidget);
    expect(find.byType(TextField), findsAtLeastNWidgets(6));
    expect(find.text('Activate on MikroTik'), findsWidgets);
    expect(find.text('App is not following'), findsNothing);
  });

  testWidgets('Reseller dashboard maps backend KPI keys and lists data', (tester) async {
    final api = FakeApiService();
    await _phone(tester, _wrap(ResellerHomeScreen(
      api: api,
      loginPayload: const {'reseller': {'name': 'Partner'}},
    )));
    await tester.pumpAndSettle();
    expect(find.text('1,234.00'), findsWidgets);
    expect(find.text('9'), findsWidgets);
    await tester.tap(find.text('Customers').last);
    await tester.pumpAndSettle();
    expect(find.text('Client A'), findsOneWidget);
  });
}
