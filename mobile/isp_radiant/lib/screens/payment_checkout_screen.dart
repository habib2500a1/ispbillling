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
  late final Uri _initialUri;

  @override
  void initState() {
    super.initState();
    _initialUri = Uri.parse(widget.paymentUrl);
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
            if (!_isAllowedUrl(request.url)) {
              return NavigationDecision.prevent;
            }
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

  bool _isAllowedUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) return false;
    if (uri.scheme != 'https') return false;
    if (uri.host.isEmpty) return false;

    final host = uri.host.toLowerCase();
    final originHost = _initialUri.host.toLowerCase();
    final isSameHost = host == originHost || host.endsWith('.$originHost');

    return isSameHost;
  }

  bool _isPaymentDone(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) return false;
    if (!_isAllowedUrl(url)) return false;
    final joined = '${uri.path}?${uri.query}'.toLowerCase();

    return joined.contains('callback')
        || joined.contains('return')
        || joined.contains('payment-success')
        || uri.queryParameters['status']?.toLowerCase() == 'success'
        || uri.queryParameters['payment_status']?.toLowerCase() == 'success';
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
            child: Text('In-app payment gateway', style: TextStyle(fontSize: 11)),
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
