import 'package:flutter_test/flutter_test.dart';
import 'package:mfs_verify/main.dart';

void main() {
  testWidgets('RCL SMS app boots', (WidgetTester tester) async {
    await tester.pumpWidget(const MfsVerifyApp());
    await tester.pump();

    expect(find.text('RCL SMS'), findsWidgets);
  });
}
