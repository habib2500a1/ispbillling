//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class ApiKeyCreateRequest {
  /// Returns a new [ApiKeyCreateRequest] instance.
  ApiKeyCreateRequest({
    required this.name,
    this.abilities = const [],
  });

  String name;

  /// Empty = full read scope. Subset of assignable portal permissions.
  List<ApiKeyAbility> abilities;

  @override
  bool operator ==(Object other) => identical(this, other) || other is ApiKeyCreateRequest &&
    other.name == name &&
    _deepEquality.equals(other.abilities, abilities);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (name.hashCode) +
    (abilities.hashCode);

  @override
  String toString() => 'ApiKeyCreateRequest[name=$name, abilities=$abilities]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'name'] = this.name;
      json[r'abilities'] = this.abilities;
    return json;
  }

  /// Returns a new [ApiKeyCreateRequest] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static ApiKeyCreateRequest? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "ApiKeyCreateRequest[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "ApiKeyCreateRequest[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return ApiKeyCreateRequest(
        name: mapValueOfType<String>(json, r'name')!,
        abilities: ApiKeyAbility.listFromJson(json[r'abilities']),
      );
    }
    return null;
  }

  static List<ApiKeyCreateRequest> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <ApiKeyCreateRequest>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = ApiKeyCreateRequest.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, ApiKeyCreateRequest> mapFromJson(dynamic json) {
    final map = <String, ApiKeyCreateRequest>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = ApiKeyCreateRequest.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of ApiKeyCreateRequest-objects as value to a dart map
  static Map<String, List<ApiKeyCreateRequest>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<ApiKeyCreateRequest>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = ApiKeyCreateRequest.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
    'name',
  };
}

