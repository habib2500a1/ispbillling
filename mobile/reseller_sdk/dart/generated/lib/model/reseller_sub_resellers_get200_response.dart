//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class ResellerSubResellersGet200Response {
  /// Returns a new [ResellerSubResellersGet200Response] instance.
  ResellerSubResellersGet200Response({
    this.partners = const [],
  });

  List<Map<String, Object>> partners;

  @override
  bool operator ==(Object other) => identical(this, other) || other is ResellerSubResellersGet200Response &&
    _deepEquality.equals(other.partners, partners);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (partners.hashCode);

  @override
  String toString() => 'ResellerSubResellersGet200Response[partners=$partners]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'partners'] = this.partners;
    return json;
  }

  /// Returns a new [ResellerSubResellersGet200Response] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static ResellerSubResellersGet200Response? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "ResellerSubResellersGet200Response[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "ResellerSubResellersGet200Response[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return ResellerSubResellersGet200Response(
        partners: Map.listFromJson(json[r'partners']),
      );
    }
    return null;
  }

  static List<ResellerSubResellersGet200Response> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <ResellerSubResellersGet200Response>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = ResellerSubResellersGet200Response.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, ResellerSubResellersGet200Response> mapFromJson(dynamic json) {
    final map = <String, ResellerSubResellersGet200Response>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = ResellerSubResellersGet200Response.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of ResellerSubResellersGet200Response-objects as value to a dart map
  static Map<String, List<ResellerSubResellersGet200Response>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<ResellerSubResellersGet200Response>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = ResellerSubResellersGet200Response.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

