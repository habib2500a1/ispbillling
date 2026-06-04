//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class PaymentCollectRequest {
  /// Returns a new [PaymentCollectRequest] instance.
  PaymentCollectRequest({
    required this.amount,
    this.method,
    this.reference,
    this.notes,
    this.invoiceId,
  });

  /// Minimum value: 0
  num amount;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  PaymentMethod? method;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? reference;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? notes;

  /// Optional invoice to apply payment against
  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? invoiceId;

  @override
  bool operator ==(Object other) => identical(this, other) || other is PaymentCollectRequest &&
    other.amount == amount &&
    other.method == method &&
    other.reference == reference &&
    other.notes == notes &&
    other.invoiceId == invoiceId;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (amount.hashCode) +
    (method == null ? 0 : method!.hashCode) +
    (reference == null ? 0 : reference!.hashCode) +
    (notes == null ? 0 : notes!.hashCode) +
    (invoiceId == null ? 0 : invoiceId!.hashCode);

  @override
  String toString() => 'PaymentCollectRequest[amount=$amount, method=$method, reference=$reference, notes=$notes, invoiceId=$invoiceId]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'amount'] = this.amount;
    if (this.method != null) {
      json[r'method'] = this.method;
    } else {
      json[r'method'] = null;
    }
    if (this.reference != null) {
      json[r'reference'] = this.reference;
    } else {
      json[r'reference'] = null;
    }
    if (this.notes != null) {
      json[r'notes'] = this.notes;
    } else {
      json[r'notes'] = null;
    }
    if (this.invoiceId != null) {
      json[r'invoice_id'] = this.invoiceId;
    } else {
      json[r'invoice_id'] = null;
    }
    return json;
  }

  /// Returns a new [PaymentCollectRequest] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static PaymentCollectRequest? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "PaymentCollectRequest[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "PaymentCollectRequest[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return PaymentCollectRequest(
        amount: num.parse('${json[r'amount']}'),
        method: PaymentMethod.fromJson(json[r'method']),
        reference: mapValueOfType<String>(json, r'reference'),
        notes: mapValueOfType<String>(json, r'notes'),
        invoiceId: mapValueOfType<int>(json, r'invoice_id'),
      );
    }
    return null;
  }

  static List<PaymentCollectRequest> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <PaymentCollectRequest>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = PaymentCollectRequest.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, PaymentCollectRequest> mapFromJson(dynamic json) {
    final map = <String, PaymentCollectRequest>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = PaymentCollectRequest.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of PaymentCollectRequest-objects as value to a dart map
  static Map<String, List<PaymentCollectRequest>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<PaymentCollectRequest>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = PaymentCollectRequest.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
    'amount',
  };
}

