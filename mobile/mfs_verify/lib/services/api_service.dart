import 'dart:convert';

import 'package:http/http.dart' as http;

import '../utils/api_url_normalizer.dart';

class MfsApiException implements Exception {
  MfsApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class MfsApiService {
  MfsApiService({required String baseUrl, required this.deviceKey})
      : baseUrl = ApiUrlNormalizer.normalize(baseUrl);

  final String baseUrl;
  final String deviceKey;

  String get ingestUrl => ApiUrlNormalizer.ingestUrl(baseUrl);

  Uri get _ingestUri => Uri.parse(ingestUrl);

  Future<Map<String, dynamic>> ingest({
    required String gateway,
    required String transactionId,
    required double amount,
    String? senderPhone,
    String? customerReference,
    String? rawMessage,
    String? deviceName,
  }) async {
    final res = await http
        .post(
          _ingestUri,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-MFS-Device-Key': deviceKey,
          },
          body: jsonEncode({
            'gateway': gateway,
            'transaction_id': transactionId,
            'amount': amount,
            if (senderPhone != null && senderPhone.isNotEmpty) 'sender_phone': senderPhone,
            if (customerReference != null && customerReference.isNotEmpty)
              'customer_reference': customerReference,
            if (rawMessage != null && rawMessage.isNotEmpty) 'raw_message': rawMessage,
            if (deviceName != null && deviceName.isNotEmpty) 'device_name': deviceName,
          }),
        )
        .timeout(const Duration(seconds: 45));

    Map<String, dynamic>? body;
    try {
      body = jsonDecode(res.body) as Map<String, dynamic>?;
    } catch (_) {}

    if (res.statusCode >= 200 && res.statusCode < 300) {
      return body ?? {'ok': true};
    }

    final msg = body?['message']?.toString() ?? 'Request failed (${res.statusCode})';
    throw MfsApiException(msg, statusCode: res.statusCode);
  }
}
