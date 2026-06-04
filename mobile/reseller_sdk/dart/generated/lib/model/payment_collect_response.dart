//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class PaymentCollectResponse {
  /// Returns a new [PaymentCollectResponse] instance.
  PaymentCollectResponse({
    this.payment = const {},
    this.receiptUrl,
  });

  Map<String, Object> payment;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? receiptUrl;

  @override
  bool operator ==(Object other) => identical(this, other) || other is PaymentCollectResponse &&
    _deepEquality.equals(other.payment, payment) &&
    other.receiptUrl == receiptUrl;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (payment.hashCode) +
    (receiptUrl == null ? 0 : receiptUrl!.hashCode);

  @override
  String toString() => 'PaymentCollectResponse[payment=$payment, receiptUrl=$receiptUrl]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'payment'] = this.payment;
    if (this.receiptUrl != null) {
      json[r'receipt_url'] = this.receiptUrl;
    } else {
      json[r'receipt_url'] = null;
    }
    return json;
  }

  /// Returns a new [PaymentCollectResponse] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static PaymentCollectResponse? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "PaymentCollectResponse[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "PaymentCollectResponse[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return PaymentCollectResponse(
        payment: mapCastOfType<String, Object>(json, r'payment') ?? const {},
        receiptUrl: mapValueOfType<String>(json, r'receipt_url'),
      );
    }
    return null;
  }

  static List<PaymentCollectResponse> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <PaymentCollectResponse>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = PaymentCollectResponse.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, PaymentCollectResponse> mapFromJson(dynamic json) {
    final map = <String, PaymentCollectResponse>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = PaymentCollectResponse.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of PaymentCollectResponse-objects as value to a dart map
  static Map<String, List<PaymentCollectResponse>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<PaymentCollectResponse>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = PaymentCollectResponse.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

