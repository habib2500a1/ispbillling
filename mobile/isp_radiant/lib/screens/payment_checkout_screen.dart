import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// bKash payment page inside the app (not Chrome/browser). User stays in the app.
class PaymentCheckoutScreen extends StatefulWidget {
  const PaymentCheckoutScreen({super.key, required this.paymentUrl, required this.title});

  final String paymentUrl;
  final String title;

  @override
  State<PaymentCheckoutScreen> createState() => _PaymentCheckoutScreenState();
}

class _PaymentCheckoutScreenState extends State<PaymentCheckoutScreen> {
  late final WebViewController _controller;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) => setState(() => _loading = true),
          onPageFinished: (url) {
            setState(() => _loading = false);
            _maybeComplete(url);
          },
          onNavigationRequest: (request) {
            if (_isPaymentDone(request.url)) {
              if (mounted) Navigator.pop(context, true);
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.paymentUrl));
  }

  bool _isPaymentDone(String url) {
    final u = url.toLowerCase();
    return u.contains('success') || u.contains('callback') || u.contains('complete') || u.contains('paid');
  }

  void _maybeComplete(String url) {
    if (_isPaymentDone(url) && mounted) Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(28),
          child: Padding(
            padding: EdgeInsets.only(bottom: 8),
            child: Text('Payment gateway — অ্যাপের ভিতরেই', style: TextStyle(fontSize: 11)),
          ),
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading) const LinearProgressIndicator(minHeight: 3),
        ],
      ),
    );
  }
}
