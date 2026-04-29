-- _call_forward.lua
-- Call forward helper for *72/*73/*74 feature codes.

local action = argv[1] or 'activate'
local destination_arg = argv[2]

local function trim(value)
    if not value then
        return ''
    end

    return (value:gsub('^%s+', ''):gsub('%s+$', ''))
end

local function sql_escape(value)
    if value == nil then
        return ''
    end

    return tostring(value):gsub("'", "''")
end

local function empty(value)
    return trim(value) == ''
end

local function quote_or_null(value)
    if value == nil or empty(value) then
        return 'NULL'
    end

    return "'" .. sql_escape(trim(value)) .. "'"
end

local function normalize_forward_number(value)
    value = trim(value)
    if value == '' then
        return nil
    end

    local cleaned = value:gsub('[%s%-%(%)%.]+', '')

    if cleaned:match('^%+[1-9]%d%d%d%d%d%d%d+$') then
        return cleaned
    end

    cleaned = cleaned:gsub('^%+', '')
    cleaned = cleaned:gsub('^00', '')
    cleaned = cleaned:gsub('^011', '')

    if cleaned:match('^[1-9]%d%d%d%d%d%d+$') then
        if #cleaned > 10 then
            return '+' .. cleaned
        end

        return '+1' .. cleaned
    end

    if cleaned ~= '' then
        return '+1' .. cleaned
    end

    return nil
end

local function prompt_destination()
    local digits = session:playAndGetDigits(
        7,
        20,
        3,
        5000,
        '#',
        'phrase:enter_destination',
        '',
        '[0-9+]+',
        3000
    )

    return trim(digits)
end

local function respond_and_log(level, message, phrase)
    freeswitch.consoleLog(level, string.format('[_call_forward] %s\n', message))
    if phrase and session:ready() then
        session:streamFile(phrase)
    end
end

if not session:ready() then
    return
end

session:answer()

local domain_name = session:getVariable('domain_name') or session:getVariable('context') or 'default'
local caller_id_number = trim(session:getVariable('caller_id_number') or '')
local caller_extension = trim(session:getVariable('sip_from_user') or caller_id_number)

if caller_extension == '' then
    respond_and_log('WARNING', 'missing caller extension', 'phrase:feature_not_available')
    return
end

local dbh = freeswitch.Dbh('odbc://nizam')
if not dbh or not dbh:connected() then
    respond_and_log('ERR', 'unable to connect to database via ODBC DSN nizam', 'phrase:feature_not_available')
    return
end

local escaped_domain = sql_escape(domain_name)
local escaped_extension = sql_escape(caller_extension)
local extension_record = nil

local extension_query = string.format([[
SELECT
    e.id,
    e.organization_id,
    e.extension,
    e.follow_me_enabled,
    e.follow_me_destination,
    e.dnd_enabled,
    a.id AS agent_id
FROM extensions e
INNER JOIN organizations o ON o.id = e.organization_id
LEFT JOIN agents a ON a.extension_id = e.id
WHERE o.domain = '%s'
  AND e.extension = '%s'
  AND e.is_active = true
LIMIT 1
]], escaped_domain, escaped_extension)

assert(dbh:query(extension_query, function(row)
    extension_record = row
end))

if not extension_record then
    dbh:release()
    respond_and_log('WARNING', string.format('extension %s@%s not found', caller_extension, domain_name), 'phrase:feature_not_available')
    return
end

local organization_id = extension_record.organization_id
local extension_id = extension_record.id
local agent_id = extension_record.agent_id
local stored_destination = trim(extension_record.follow_me_destination or '')
local destination = destination_arg and trim(destination_arg) or ''

if action == 'activate' and destination == '' then
    destination = prompt_destination()
end

if action == 'restore' then
    destination = stored_destination
end

if action == 'activate' or action == 'restore' then
    if destination == '' then
        dbh:release()
        respond_and_log('WARNING', string.format('missing forward destination for %s action', action), 'phrase:invalid_entry')
        return
    end
end

local normalized_destination = normalize_forward_number(destination)
if (action == 'activate' or action == 'restore') and not normalized_destination then
    dbh:release()
    respond_and_log('WARNING', string.format('unable to normalize forward destination [%s]', destination), 'phrase:invalid_entry')
    return
end

local update_sql = nil
if action == 'disable' then
    update_sql = string.format([[
UPDATE extensions
SET follow_me_enabled = false,
    updated_at = CURRENT_TIMESTAMP
WHERE id = '%s'
]], sql_escape(extension_id))
elseif action == 'activate' or action == 'restore' then
    update_sql = string.format([[
UPDATE extensions
SET follow_me_enabled = true,
    follow_me_destination = '%s',
    dnd_enabled = false,
    updated_at = CURRENT_TIMESTAMP
WHERE id = '%s'
]], sql_escape(destination), sql_escape(extension_id))
else
    dbh:release()
    respond_and_log('WARNING', string.format('unsupported action [%s]', action), 'phrase:feature_not_available')
    return
end

assert(dbh:query(update_sql))

local delete_binding_sql = string.format([[
DELETE FROM endpoint_bindings
WHERE organization_id = '%s'
  AND type = 'pstn_forward'
  AND (
      extension_id = '%s'%s
  )
]],
    sql_escape(organization_id),
    sql_escape(extension_id),
    agent_id and agent_id ~= '' and string.format(" OR agent_id = '%s'", sql_escape(agent_id)) or ''
)
assert(dbh:query(delete_binding_sql))

if action == 'activate' or action == 'restore' then
    local binding_insert_sql = string.format([[
INSERT INTO endpoint_bindings (
    id,
    organization_id,
    extension_id,
    agent_id,
    type,
    device_uuid,
    push_token,
    voip_push_token,
    platform,
    is_push_capable,
    is_enabled,
    rings_immediately_when_online,
    allow_late_join_after_push,
    forward_number,
    forward_requires_confirm,
    metadata,
    created_at,
    updated_at
)
VALUES (
    gen_random_uuid(),
    '%s',
    '%s',
    %s,
    'pstn_forward',
    'follow-me:%s',
    NULL,
    NULL,
    'unknown',
    false,
    true,
    false,
    false,
    '%s',
    true,
    '{"source":"follow_me","managed_by":"App\\\\Services\\\\FollowMeEndpointBindingService"}',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
)
]],
        sql_escape(organization_id),
        sql_escape(extension_id),
        quote_or_null(agent_id),
        sql_escape(extension_id),
        sql_escape(normalized_destination)
    )
    assert(dbh:query(binding_insert_sql))
end

local manifest_sql = string.format([[
UPDATE organization_dialplan_manifests
SET is_active = false,
    updated_at = CURRENT_TIMESTAMP
WHERE organization_id = '%s'
  AND manifest_type = 'inbound_routing'
  AND is_active = true
]], sql_escape(organization_id))
assert(dbh:query(manifest_sql))

dbh:release()

if action == 'disable' then
    respond_and_log('INFO', string.format('disabled call forwarding for %s@%s', caller_extension, domain_name), 'phrase:deactivated')
    return
end

respond_and_log('INFO', string.format('set call forwarding for %s@%s to %s', caller_extension, domain_name, normalized_destination), 'phrase:activated')
