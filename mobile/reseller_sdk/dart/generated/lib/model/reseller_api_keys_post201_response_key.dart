//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class ResellerApiKeysPost201ResponseKey {
  /// Returns a new [ResellerApiKeysPost201ResponseKey] instance.
  ResellerApiKeysPost201ResponseKey({
    this.id,
    this.name,
    this.keyPrefix,
    this.plainKey,
  });

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? id;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? name;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? keyPrefix;

  /// Store immediately; not retrievable later
  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? plainKey;

  @override
  bool operator ==(Object other) => identical(this, other) || other is ResellerApiKeysPost201ResponseKey &&
    other.id == id &&
    other.name == name &&
    other.keyPrefix == keyPrefix &&
    other.plainKey == plainKey;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (id == null ? 0 : id!.hashCode) +
    (name == null ? 0 : name!.hashCode) +
    (keyPrefix == null ? 0 : keyPrefix!.hashCode) +
    (plainKey == null ? 0 : plainKey!.hashCode);

  @override
  String toString() => 'ResellerApiKeysPost201ResponseKey[id=$id, name=$name, keyPrefix=$keyPrefix, plainKey=$plainKey]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.id != null) {
      json[r'id'] = this.id;
    } else {
      json[r'id'] = null;
    }
    if (this.name != null) {
      json[r'name'] = this.name;
    } else {
      json[r'name'] = null;
    }
    if (this.keyPrefix != null) {
      json[r'key_prefix'] = this.keyPrefix;
    } else {
      json[r'key_prefix'] = null;
    }
    if (this.plainKey != null) {
      json[r'plain_key'] = this.plainKey;
    } else {
      json[r'plain_key'] = null;
    }
    return json;
  }

  /// Returns a new [ResellerApiKeysPost201ResponseKey] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static ResellerApiKeysPost201ResponseKey? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "ResellerApiKeysPost201ResponseKey[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "ResellerApiKeysPost201ResponseKey[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return ResellerApiKeysPost201ResponseKey(
        id: mapValueOfType<int>(json, r'id'),
        name: mapValueOfType<String>(json, r'name'),
        keyPrefix: mapValueOfType<String>(json, r'key_prefix'),
        plainKey: mapValueOfType<String>(json, r'plain_key'),
      );
    }
    return null;
  }

  static List<ResellerApiKeysPost201ResponseKey> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <ResellerApiKeysPost201ResponseKey>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = ResellerApiKeysPost201ResponseKey.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, ResellerApiKeysPost201ResponseKey> mapFromJson(dynamic json) {
    final map = <String, ResellerApiKeysPost201ResponseKey>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = ResellerApiKeysPost201ResponseKey.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of ResellerApiKeysPost201ResponseKey-objects as value to a dart map
  static Map<String, List<ResellerApiKeysPost201ResponseKey>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<ResellerApiKeysPost201ResponseKey>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = ResellerApiKeysPost201ResponseKey.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}

