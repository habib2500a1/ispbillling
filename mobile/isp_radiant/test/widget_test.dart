import 'package:flutter_test/flutter_test.dart';
import 'package:isp_radiant/main.dart';

void main() {
  testWidgets('App boots to splash gate', (WidgetTester tester) async {
    await tester.pumpWidget(const IspRadiantApp());
    await tester.pump();
    expect(find.text('RADIANT ISP'), findsNothing);
  });
}
