//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class MeResponse {
  /// Returns a new [MeResponse] instance.
  MeResponse({
    this.reseller = const {},
    this.actor,
    this.permissions = const [],
  });

  Map<String, Object> reseller;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  MeResponseActor? actor;

  List<String> permissions;

  @override
  bool operator ==(Object other) => identical(this, other) || other is MeResponse &&
    _deepEquality.equals(other.reseller, reseller) &&
    other.actor == actor &&
    _deepEquality.equals(other.permissions, permissions);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (reseller.hashCode) +
    (actor == null ? 0 : actor!.hashCode) +
    (permissions.hashCode);

  @override
  String toString() => 'MeResponse[reseller=$reseller, actor=$actor, permissions=$permissions]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'reseller'] = this.reseller;
    if (this.actor != null) {
      json[r'actor'] = this.actor;
    } else {
      json[r'actor'] = null;
    }
      json[r'permissions'] = this.permissions;
    return json;
  }

  /// Returns a new [MeResponse] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static MeResponse? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "MeResponse[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "MeResponse[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return MeResponse(
        reseller: mapCastOfType<String, Object>(json, r'reseller') ?? const {},
        actor: MeResponseActor.fromJson(json[r'actor']),
        permissions: json[r'permissions'] is Iterable
            ? (json[r'permissions'] as Iterable).cast<String>().toList(growable: false)
            : const [],
      );
    }
    return null;
  }

  static List<MeResponse> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <MeResponse>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = MeResponse.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, MeResponse> mapFromJson(dynamic json) {
    final map = <String, MeResponse>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = MeResponse.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of MeResponse-objects as value to a dart map
  static Map<String, List<MeResponse>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<MeResponse>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = MeResponse.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

