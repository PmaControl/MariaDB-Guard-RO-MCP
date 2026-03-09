#!/usr/bin/env bash
set -euo pipefail

parse_test_file() {
  local test_file="$1"
  local inherited_db_engine="${DB_ENGINE-__UNSET__}"
  local inherited_db_version="${DB_VERSION-__UNSET__}"
  local inherited_ssl_mode="${SSL_MODE-__UNSET__}"
  local inherited_guardian="${GUARDIAN-__UNSET__}"
  local inherited_stack="${STACK-__UNSET__}"
  local inherited_mcp_endpoint="${MCP_ENDPOINT-__UNSET__}"
  local inherited_mcp_token="${MCP_TOKEN-__UNSET__}"

  unset TEST_ID TEST_NAME TEST_DESCRIPTION BLOCK TAGS PRIORITY TIMEOUT_S RETRIES
  unset DB_ENGINE DB_VERSION SSL_MODE GUARDIAN STACK MCP_ENDPOINT MCP_TOKEN
  unset PR_ID MCP_ID PRE_HOOK POST_HOOK COMMAND EXPECT_JSON_PATH EXPECT_EQUALS
  unset ENABLED MATRIX_EXPAND DB_VERSION_LIST SSL_MODE_LIST

  if [ "$inherited_db_engine" != "__UNSET__" ]; then DB_ENGINE="$inherited_db_engine"; fi
  if [ "$inherited_db_version" != "__UNSET__" ]; then DB_VERSION="$inherited_db_version"; fi
  if [ "$inherited_ssl_mode" != "__UNSET__" ]; then SSL_MODE="$inherited_ssl_mode"; fi
  if [ "$inherited_guardian" != "__UNSET__" ]; then GUARDIAN="$inherited_guardian"; fi
  if [ "$inherited_stack" != "__UNSET__" ]; then STACK="$inherited_stack"; fi
  if [ "$inherited_mcp_endpoint" != "__UNSET__" ]; then MCP_ENDPOINT="$inherited_mcp_endpoint"; fi
  if [ "$inherited_mcp_token" != "__UNSET__" ]; then MCP_TOKEN="$inherited_mcp_token"; fi

  # shellcheck source=/dev/null
  source "$test_file"

  : "${TEST_ID:?TEST_ID manquant dans $test_file}"
  : "${TEST_NAME:?TEST_NAME manquant dans $test_file}"
  : "${BLOCK:?BLOCK manquant dans $test_file}"
  : "${DB_ENGINE:?DB_ENGINE manquant dans $test_file}"
  : "${DB_VERSION:?DB_VERSION manquant dans $test_file}"
  : "${SSL_MODE:?SSL_MODE manquant dans $test_file}"
  : "${GUARDIAN:?GUARDIAN manquant dans $test_file}"
  : "${STACK:?STACK manquant dans $test_file}"
  : "${PR_ID:?PR_ID manquant dans $test_file}"
  : "${MCP_ID:?MCP_ID manquant dans $test_file}"
  : "${MCP_ENDPOINT:?MCP_ENDPOINT manquant dans $test_file}"
  : "${COMMAND:?COMMAND manquant dans $test_file}"

  TEST_DESCRIPTION="${TEST_DESCRIPTION:-}"
  TAGS="${TAGS:-}"
  PRIORITY="${PRIORITY:-P2}"
  TIMEOUT_S="${TIMEOUT_S:-30}"
  RETRIES="${RETRIES:-0}"
  PRE_HOOK="${PRE_HOOK:-}"
  POST_HOOK="${POST_HOOK:-}"
  MCP_TOKEN="${MCP_TOKEN:-}"
  EXPECT_JSON_PATH="${EXPECT_JSON_PATH:-}"
  EXPECT_EQUALS="${EXPECT_EQUALS:-}"
  ENABLED="${ENABLED:-true}"
  MATRIX_EXPAND="${MATRIX_EXPAND:-false}"
  DB_VERSION_LIST="${DB_VERSION_LIST:-$DB_VERSION}"
  SSL_MODE_LIST="${SSL_MODE_LIST:-$SSL_MODE}"
}

render_resolved_config() {
  cat <<CFG
TEST_ID=$TEST_ID
TEST_NAME=$TEST_NAME
TEST_DESCRIPTION=$TEST_DESCRIPTION
BLOCK=$BLOCK
TAGS=$TAGS
PRIORITY=$PRIORITY
TIMEOUT_S=$TIMEOUT_S
RETRIES=$RETRIES
DB_ENGINE=$DB_ENGINE
DB_VERSION=$DB_VERSION
SSL_MODE=$SSL_MODE
GUARDIAN=$GUARDIAN
STACK=$STACK
PR_ID=$PR_ID
MCP_ID=$MCP_ID
MCP_ENDPOINT=$MCP_ENDPOINT
MCP_TOKEN=${MCP_TOKEN:+***}
PRE_HOOK=$PRE_HOOK
POST_HOOK=$POST_HOOK
COMMAND=$COMMAND
EXPECT_JSON_PATH=$EXPECT_JSON_PATH
EXPECT_EQUALS=$EXPECT_EQUALS
ENABLED=$ENABLED
MATRIX_EXPAND=$MATRIX_EXPAND
DB_VERSION_LIST=$DB_VERSION_LIST
SSL_MODE_LIST=$SSL_MODE_LIST
CFG
}
