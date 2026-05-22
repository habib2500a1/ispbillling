/// Parse bKash / Nagad / Rocket payment SMS (Bangladesh MFS).
class MfsSmsParseResult {
  MfsSmsParseResult({
    this.gateway,
    this.transactionId,
    this.amount,
    this.senderPhone,
    this.customerReference,
    this.skipReason,
  });

  final String? gateway;
  final String? transactionId;
  final double? amount;
  final String? senderPhone;
  /// Subscriber ID / PPPoE typed in payment reference (Counter) field.
  final String? customerReference;
  final String? skipReason;

  bool get isValid =>
      gateway != null &&
      transactionId != null &&
      amount != null &&
      amount! > 0;
}

class MfsSmsParser {
  /// Known MFS SMS sender IDs (Bangladesh).
  static const mfsSenderHints = ['16247', '16167', '16216', '247247', 'bkash', 'nagad', 'rocket'];

  static bool looksLikeMfsSender(String? address) {
    if (address == null || address.isEmpty) return false;
    final a = address.toLowerCase();
    return mfsSenderHints.any((h) => a.contains(h));
  }

  static MfsSmsParseResult parse(String text) {
    final body = text.trim();
    if (body.isEmpty) {
      return MfsSmsParseResult(skipReason: 'empty');
    }

    final lower = body.toLowerCase();
    if (RegExp(r'\botp\b|verification code|one time password', caseSensitive: false).hasMatch(body)) {
      return MfsSmsParseResult(skipReason: 'otp');
    }

    String? gateway;
    if (lower.contains('bkash') || lower.contains('b-kash')) {
      gateway = 'bkash';
    } else if (lower.contains('nagad')) {
      gateway = 'nagad';
    } else if (lower.contains('rocket')) {
      gateway = 'rocket';
    } else if (RegExp(r'cash\s*in|you have received|received tk|send money', caseSensitive: false)
        .hasMatch(body)) {
      gateway = 'bkash';
    } else {
      return MfsSmsParseResult(skipReason: 'not_mfs');
    }

    final upper = body.toUpperCase();
    String? trx;
    final trxMatch = RegExp(
      r'(?:TRX(?:ID)?|TXN(?:ID)?|TRANSACTION)\s*(?:ID|NO)?[\s:#-]*([A-Z0-9]{6,20})',
      caseSensitive: false,
    ).firstMatch(upper);
    if (trxMatch != null) {
      trx = trxMatch.group(1)?.toUpperCase();
    } else {
      final refMatch = RegExp(r'\bREF[\s:#-]*([A-Z0-9]{6,20})\b', caseSensitive: false).firstMatch(upper);
      trx = refMatch?.group(1)?.toUpperCase();
    }

    final amount = _parseAmount(body);
    if (amount == null || amount <= 0) {
      return MfsSmsParseResult(gateway: gateway, skipReason: 'no_amount');
    }

    if (trx == null || trx.length < 6) {
      return MfsSmsParseResult(gateway: gateway, amount: amount, skipReason: 'no_trxid');
    }

    String? phone;
    final phoneMatch = RegExp(r'01[3-9]\d{8}').firstMatch(body);
    phone = phoneMatch?.group(0);

    final customerRef = _parseCustomerReference(body, trx);

    return MfsSmsParseResult(
      gateway: gateway,
      transactionId: trx,
      amount: amount,
      senderPhone: phone,
      customerReference: customerRef,
    );
  }

  static String? _parseCustomerReference(String body, String trxId) {
    for (final pattern in [
      RegExp(
        r'\b(?:reference|counter|note|remarks?|memo)\s*[:\s#-]+([A-Za-z0-9@._-]{2,64})',
        caseSensitive: false,
      ),
      RegExp(r'\bRef\s*[:\s#-]+([A-Za-z0-9@._-]{2,64})', caseSensitive: false),
    ]) {
      final m = pattern.firstMatch(body);
      if (m != null) {
        var token = m.group(1)?.trim();
        if (token != null && token.isNotEmpty) {
          token = token.replaceAll(RegExp(r'[.,;:#-]+$'), '');
          if (token.isEmpty || token.toUpperCase() == trxId.toUpperCase()) {
            continue;
          }
          if (_isPhone(token) || (RegExp(r'^\d+$').hasMatch(token) && token.length >= 8)) {
            continue;
          }
          return token;
        }
      }
    }

    final pppoe = RegExp(r'[\w][\w.-]{1,62}@[\w][\w.-]{1,62}').firstMatch(body);
    if (pppoe != null) {
      return pppoe.group(0);
    }

    return null;
  }

  static bool _isPhone(String token) {
    return RegExp(r'^01[3-9]\d{8}$').hasMatch(token);
  }

  static double? _parseAmount(String body) {
    final normalized = body.replaceAllMapped(
      RegExp(r'(\d),(?=\d{3})'),
      (m) => m.group(1)!,
    );

    for (final pattern in [
      RegExp(
        r'(?:TK|TAKA|BDT|AMOUNT)\s*[:\s]*([0-9]+(?:\.[0-9]{1,2})?)',
        caseSensitive: false,
      ),
      RegExp(
        r'([0-9]+(?:\.[0-9]{1,2})?)\s*(?:TK|TAKA|BDT)',
        caseSensitive: false,
      ),
      RegExp(
        r'(?:received|credited|cash\s*in)\s*(?:tk|taka|bdt)?\s*([0-9]+(?:\.[0-9]{1,2})?)',
        caseSensitive: false,
      ),
    ]) {
      final m = pattern.firstMatch(normalized);
      if (m != null) {
        final v = double.tryParse(m.group(1) ?? '');
        if (v != null && v > 0) return v;
      }
    }
    return null;
  }
}
