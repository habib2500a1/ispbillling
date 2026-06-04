//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class SettlementCreateRequest {
  /// Returns a new [SettlementCreateRequest] instance.
  SettlementCreateRequest({
    required this.amount,
    this.expenseDeduction,
    this.notes,
    this.paymentMethod,
    this.reference,
  });

  /// Minimum value: 0.01
  num amount;

  /// Minimum value: 0
  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  num? expenseDeduction;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? notes;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? paymentMethod;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? reference;

  @override
  bool operator ==(Object other) => identical(this, other) || other is SettlementCreateRequest &&
    other.amount == amount &&
    other.expenseDeduction == expenseDeduction &&
    other.notes == notes &&
    other.paymentMethod == paymentMethod &&
    other.reference == reference;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (amount.hashCode) +
    (expenseDeduction == null ? 0 : expenseDeduction!.hashCode) +
    (notes == null ? 0 : notes!.hashCode) +
    (paymentMethod == null ? 0 : paymentMethod!.hashCode) +
    (reference == null ? 0 : reference!.hashCode);

  @override
  String toString() => 'SettlementCreateRequest[amount=$amount, expenseDeduction=$expenseDeduction, notes=$notes, paymentMethod=$paymentMethod, reference=$reference]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'amount'] = this.amount;
    if (this.expenseDeduction != null) {
      json[r'expense_deduction'] = this.expenseDeduction;
    } else {
      json[r'expense_deduction'] = null;
    }
    if (this.notes != null) {
      json[r'notes'] = this.notes;
    } else {
      json[r'notes'] = null;
    }
    if (this.paymentMethod != null) {
      json[r'payment_method'] = this.paymentMethod;
    } else {
      json[r'payment_method'] = null;
    }
    if (this.reference != null) {
      json[r'reference'] = this.reference;
    } else {
      json[r'reference'] = null;
    }
    return json;
  }

  /// Returns a new [SettlementCreateRequest] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static SettlementCreateRequest? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "SettlementCreateRequest[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "SettlementCreateRequest[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return SettlementCreateRequest(
        amount: num.parse('${json[r'amount']}'),
        expenseDeduction: num.parse('${json[r'expense_deduction']}'),
        notes: mapValueOfType<String>(json, r'notes'),
        paymentMethod: mapValueOfType<String>(json, r'payment_method'),
        reference: mapValueOfType<String>(json, r'reference'),
      );
    }
    return null;
  }

  static List<SettlementCreateRequest> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <SettlementCreateRequest>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = SettlementCreateRequest.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, SettlementCreateRequest> mapFromJson(dynamic json) {
    final map = <String, SettlementCreateRequest>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = SettlementCreateRequest.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of SettlementCreateRequest-objects as value to a dart map
  static Map<String, List<SettlementCreateRequest>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<SettlementCreateRequest>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = SettlementCreateRequest.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
    'amount',
  };
}

