//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

library isp_reseller_api;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:collection/collection.dart';
import 'package:http/http.dart';
import 'package:intl/intl.dart';
import 'package:meta/meta.dart';

part 'api_client.dart';
part 'api_helper.dart';
part 'api_exception.dart';
part 'auth/authentication.dart';
part 'auth/api_key_auth.dart';
part 'auth/oauth.dart';
part 'auth/http_basic_auth.dart';
part 'auth/http_bearer_auth.dart';

part 'api/api_keys_sanctum_only_api.dart';
part 'api/auth_api.dart';
part 'api/read_sanctum_or_api_key_api.dart';
part 'api/write_sanctum_only_api.dart';

part 'model/api_key_ability.dart';
part 'model/api_key_create_request.dart';
part 'model/api_key_meta.dart';
part 'model/customer.dart';
part 'model/customer_create_request.dart';
part 'model/customer_update_request.dart';
part 'model/error.dart';
part 'model/inline_object.dart';
part 'model/login_request.dart';
part 'model/login_response.dart';
part 'model/me_response.dart';
part 'model/me_response_actor.dart';
part 'model/paginated_customers.dart';
part 'model/payment_collect_request.dart';
part 'model/payment_collect_response.dart';
part 'model/payment_method.dart';
part 'model/reseller_announcements_get200_response.dart';
part 'model/reseller_api_keys_get200_response.dart';
part 'model/reseller_api_keys_post201_response.dart';
part 'model/reseller_api_keys_post201_response_key.dart';
part 'model/reseller_customers_customer_patch200_response.dart';
part 'model/reseller_due_account_get200_response.dart';
part 'model/reseller_internal_tickets_get200_response.dart';
part 'model/reseller_internal_tickets_post_request.dart';
part 'model/reseller_logout_post200_response.dart';
part 'model/reseller_staff_get200_response.dart';
part 'model/reseller_staff_permission_options_get200_response.dart';
part 'model/reseller_staff_staff_member_get200_response.dart';
part 'model/reseller_sub_resellers_get200_response.dart';
part 'model/settlement_create_request.dart';
part 'model/settlement_create_response.dart';
part 'model/settlement_list_response.dart';
part 'model/staff_create_request.dart';
part 'model/staff_member.dart';
part 'model/staff_mutation_response.dart';
part 'model/staff_update_request.dart';
part 'model/wallet_pipra_pay_request.dart';
part 'model/wallet_pipra_pay_response.dart';
part 'model/wallet_recharge_request.dart';
part 'model/wallet_recharge_response.dart';


/// An [ApiClient] instance that uses the default values obtained from
/// the OpenAPI specification file.
var defaultApiClient = ApiClient();

const _delimiters = {'csv': ',', 'ssv': ' ', 'tsv': '\t', 'pipes': '|'};
const _dateEpochMarker = 'epoch';
const _deepEquality = DeepCollectionEquality();
final _dateFormatter = DateFormat('yyyy-MM-dd');
final _regList = RegExp(r'^List<(.*)>$');
final _regSet = RegExp(r'^Set<(.*)>$');
final _regMap = RegExp(r'^Map<String,(.*)>$');

bool _isEpochMarker(String? pattern) => pattern == _dateEpochMarker || pattern == '/$_dateEpochMarker/';
