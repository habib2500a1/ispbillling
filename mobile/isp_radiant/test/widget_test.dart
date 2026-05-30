import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isp_radiant/app.dart';

void main() {
  testWidgets('App boots inside ProviderScope', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: IspRadiantApp()));
    await tester.pump();
    // Splash gate renders the brand name while booting.
    expect(find.text('RADIANT ISP'), findsWidgets);
  });
}
