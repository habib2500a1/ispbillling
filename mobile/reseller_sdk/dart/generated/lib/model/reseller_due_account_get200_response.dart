//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class ResellerDueAccountGet200Response {
  /// Returns a new [ResellerDueAccountGet200Response] instance.
  ResellerDueAccountGet200Response({
    this.summary = const {},
    this.customerBreakdown = const [],
    this.aging = const {},
  });

  Map<String, Object> summary;

  List<Map<String, Object>> customerBreakdown;

  Map<String, Object> aging;

  @override
  bool operator ==(Object other) => identical(this, other) || other is ResellerDueAccountGet200Response &&
    _deepEquality.equals(other.summary, summary) &&
    _deepEquality.equals(other.customerBreakdown, customerBreakdown) &&
    _deepEquality.equals(other.aging, aging);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (summary.hashCode) +
    (customerBreakdown.hashCode) +
    (aging.hashCode);

  @override
  String toString() => 'ResellerDueAccountGet200Response[summary=$summary, customerBreakdown=$customerBreakdown, aging=$aging]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'summary'] = this.summary;
      json[r'customer_breakdown'] = this.customerBreakdown;
      json[r'aging'] = this.aging;
    return json;
  }

  /// Returns a new [ResellerDueAccountGet200Response] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static ResellerDueAccountGet200Response? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "ResellerDueAccountGet200Response[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "ResellerDueAccountGet200Response[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return ResellerDueAccountGet200Response(
        summary: mapCastOfType<String, Object>(json, r'summary') ?? const {},
        customerBreakdown: Map.listFromJson(json[r'customer_breakdown']),
        aging: mapCastOfType<String, Object>(json, r'aging') ?? const {},
      );
    }
    return null;
  }

  static List<ResellerDueAccountGet200Response> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <ResellerDueAccountGet200Response>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = ResellerDueAccountGet200Response.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, ResellerDueAccountGet200Response> mapFromJson(dynamic json) {
    final map = <String, ResellerDueAccountGet200Response>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = ResellerDueAccountGet200Response.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of ResellerDueAccountGet200Response-objects as value to a dart map
  static Map<String, List<ResellerDueAccountGet200Response>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<ResellerDueAccountGet200Response>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = ResellerDueAccountGet200Response.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

