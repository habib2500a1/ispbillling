//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class MeResponseActor {
  /// Returns a new [MeResponseActor] instance.
  MeResponseActor({
    this.type,
    this.name,
    this.staffId,
  });

  MeResponseActorTypeEnum? type;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? name;

  int? staffId;

  @override
  bool operator ==(Object other) => identical(this, other) || other is MeResponseActor &&
    other.type == type &&
    other.name == name &&
    other.staffId == staffId;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (type == null ? 0 : type!.hashCode) +
    (name == null ? 0 : name!.hashCode) +
    (staffId == null ? 0 : staffId!.hashCode);

  @override
  String toString() => 'MeResponseActor[type=$type, name=$name, staffId=$staffId]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.type != null) {
      json[r'type'] = this.type;
    } else {
      json[r'type'] = null;
    }
    if (this.name != null) {
      json[r'name'] = this.name;
    } else {
      json[r'name'] = null;
    }
    if (this.staffId != null) {
      json[r'staff_id'] = this.staffId;
    } else {
      json[r'staff_id'] = null;
    }
    return json;
  }

  /// Returns a new [MeResponseActor] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static MeResponseActor? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "MeResponseActor[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "MeResponseActor[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return MeResponseActor(
        type: MeResponseActorTypeEnum.fromJson(json[r'type']),
        name: mapValueOfType<String>(json, r'name'),
        staffId: mapValueOfType<int>(json, r'staff_id'),
      );
    }
    return null;
  }

  static List<MeResponseActor> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <MeResponseActor>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = MeResponseActor.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, MeResponseActor> mapFromJson(dynamic json) {
    final map = <String, MeResponseActor>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = MeResponseActor.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of MeResponseActor-objects as value to a dart map
  static Map<String, List<MeResponseActor>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<MeResponseActor>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = MeResponseActor.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}


class MeResponseActorTypeEnum {
  /// Instantiate a new enum with the provided [value].
  const MeResponseActorTypeEnum._(this.value);

  /// The underlying value of this enum member.
  final String value;

  @override
  String toString() => value;

  String toJson() => value;

  static const owner = MeResponseActorTypeEnum._(r'owner');
  static const staff = MeResponseActorTypeEnum._(r'staff');

  /// List of all possible values in this [enum][MeResponseActorTypeEnum].
  static const values = <MeResponseActorTypeEnum>[
    owner,
    staff,
  ];

  static MeResponseActorTypeEnum? fromJson(dynamic value) => MeResponseActorTypeEnumTypeTransformer().decode(value);

  static List<MeResponseActorTypeEnum> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <MeResponseActorTypeEnum>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = MeResponseActorTypeEnum.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }
}

/// Transformation class that can [encode] an instance of [MeResponseActorTypeEnum] to String,
/// and [decode] dynamic data back to [MeResponseActorTypeEnum].
class MeResponseActorTypeEnumTypeTransformer {
  factory MeResponseActorTypeEnumTypeTransformer() => _instance ??= const MeResponseActorTypeEnumTypeTransformer._();

  const MeResponseActorTypeEnumTypeTransformer._();

  String encode(MeResponseActorTypeEnum data) => data.value;

  /// Decodes a [dynamic value][data] to a MeResponseActorTypeEnum.
  ///
  /// If [allowNull] is true and the [dynamic value][data] cannot be decoded successfully,
  /// then null is returned. However, if [allowNull] is false and the [dynamic value][data]
  /// cannot be decoded successfully, then an [UnimplementedError] is thrown.
  ///
  /// The [allowNull] is very handy when an API changes and a new enum value is added or removed,
  /// and users are still using an old app with the old code.
  MeResponseActorTypeEnum? decode(dynamic data, {bool allowNull = true}) {
    if (data != null) {
      switch (data) {
        case r'owner': return MeResponseActorTypeEnum.owner;
        case r'staff': return MeResponseActorTypeEnum.staff;
        default:
          if (!allowNull) {
            throw ArgumentError('Unknown enum value to decode: $data');
          }
      }
    }
    return null;
  }

  /// Singleton [MeResponseActorTypeEnumTypeTransformer] instance.
  static MeResponseActorTypeEnumTypeTransformer? _instance;
}


