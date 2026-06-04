//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class SettlementListResponse {
  /// Returns a new [SettlementListResponse] instance.
  SettlementListResponse({
    this.outstandingBalance,
    this.settlements = const {},
  });

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  num? outstandingBalance;

  Map<String, Object> settlements;

  @override
  bool operator ==(Object other) => identical(this, other) || other is SettlementListResponse &&
    other.outstandingBalance == outstandingBalance &&
    _deepEquality.equals(other.settlements, settlements);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (outstandingBalance == null ? 0 : outstandingBalance!.hashCode) +
    (settlements.hashCode);

  @override
  String toString() => 'SettlementListResponse[outstandingBalance=$outstandingBalance, settlements=$settlements]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.outstandingBalance != null) {
      json[r'outstanding_balance'] = this.outstandingBalance;
    } else {
      json[r'outstanding_balance'] = null;
    }
      json[r'settlements'] = this.settlements;
    return json;
  }

  /// Returns a new [SettlementListResponse] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static SettlementListResponse? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "SettlementListResponse[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "SettlementListResponse[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return SettlementListResponse(
        outstandingBalance: num.parse('${json[r'outstanding_balance']}'),
        settlements: mapCastOfType<String, Object>(json, r'settlements') ?? const {},
      );
    }
    return null;
  }

  static List<SettlementListResponse> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <SettlementListResponse>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = SettlementListResponse.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, SettlementListResponse> mapFromJson(dynamic json) {
    final map = <String, SettlementListResponse>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = SettlementListResponse.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of SettlementListResponse-objects as value to a dart map
  static Map<String, List<SettlementListResponse>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<SettlementListResponse>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = SettlementListResponse.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

