//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class ResellerApiKeysPost201Response {
  /// Returns a new [ResellerApiKeysPost201Response] instance.
  ResellerApiKeysPost201Response({
    this.message,
    this.key,
  });

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? message;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  ResellerApiKeysPost201ResponseKey? key;

  @override
  bool operator ==(Object other) => identical(this, other) || other is ResellerApiKeysPost201Response &&
    other.message == message &&
    other.key == key;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (message == null ? 0 : message!.hashCode) +
    (key == null ? 0 : key!.hashCode);

  @override
  String toString() => 'ResellerApiKeysPost201Response[message=$message, key=$key]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.message != null) {
      json[r'message'] = this.message;
    } else {
      json[r'message'] = null;
    }
    if (this.key != null) {
      json[r'key'] = this.key;
    } else {
      json[r'key'] = null;
    }
    return json;
  }

  /// Returns a new [ResellerApiKeysPost201Response] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static ResellerApiKeysPost201Response? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "ResellerApiKeysPost201Response[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "ResellerApiKeysPost201Response[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return ResellerApiKeysPost201Response(
        message: mapValueOfType<String>(json, r'message'),
        key: ResellerApiKeysPost201ResponseKey.fromJson(json[r'key']),
      );
    }
    return null;
  }

  static List<ResellerApiKeysPost201Response> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <ResellerApiKeysPost201Response>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = ResellerApiKeysPost201Response.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, ResellerApiKeysPost201Response> mapFromJson(dynamic json) {
    final map = <String, ResellerApiKeysPost201Response>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = ResellerApiKeysPost201Response.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of ResellerApiKeysPost201Response-objects as value to a dart map
  static Map<String, List<ResellerApiKeysPost201Response>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<ResellerApiKeysPost201Response>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = ResellerApiKeysPost201Response.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

