-- _group_pickup.lua
-- Group pickup helper using organization ring-group membership as the pickup cohort.
-- Arguments: [direction]

local direction = argv[1] or 'inbound'

local function trim(value)
    if not value then
        return ''
    end

    return (value:gsub('^%s+', ''):gsub('%s+$', ''))
end

local function csv_split(value)
    local result = {}
    if not value or value == '' then
        return result
    end

    for item in string.gmatch(value, '([^,]+)') do
        local cleaned = trim(item)
        if cleaned ~= '' then
            result[#result + 1] = cleaned
        end
    end

    return result
end

local function parse_api_rows(raw)
    local rows = {}
    raw = trim(raw)
    if raw == '' or raw == '-ERR no reply' then
        return rows
    end

    for line in string.gmatch(raw, '[^\r\n]+') do
        if line ~= '' and not line:match('^%+OK') then
            local uuid, channelDirection, state, callUuid, hostname, presenceId = line:match('^(.-)|(.-)|(.-)|(.-)|(.-)|(.+)$')
            if uuid then
                rows[#rows + 1] = {
                    uuid = trim(uuid),
                    direction = trim(channelDirection),
                    state = trim(state),
                    call_uuid = trim(callUuid),
                    hostname = trim(hostname),
                    presence_id = trim(presenceId),
                }
            end
        end
    end

    return rows
end

local function channel_variable(api, uuid, name)
    if not uuid or uuid == '' or not name or name == '' then
        return nil
    end

    local response = trim(api:executeString('uuid_getvar ' .. uuid .. ' ' .. name))
    if response == '' or response == '_undef_' then
        return nil
    end

    return response
end

local api = freeswitch.API()

if not session:ready() then
    return
end

session:answer()

local domain_name = session:getVariable('domain_name') or session:getVariable('context') or 'default'
local caller_id_number = session:getVariable('caller_id_number') or ''
local caller_extension = session:getVariable('sip_from_user') or caller_id_number
local hostname = trim(api:executeString('hostname'))
local pickup_number = '*8'

local function make_proxy_call(destination, call_hostname)
    local target = destination .. '@' .. domain_name
    local dial_string = string.format('{sip_invite_domain=%s}sofia/internal/%s;fs_path=sip:%s', domain_name, target, call_hostname)
    freeswitch.consoleLog('INFO', string.format('[_group_pickup] proxying pickup %s to %s\n', destination, call_hostname))
    session:execute('bridge', dial_string)
end

local function proxy_intercept()
    local intercept_uuid = session:getVariable('sip_h_X-intercept_uuid')
    if intercept_uuid and intercept_uuid ~= '' then
        session:execute('intercept', intercept_uuid)
        return true
    end

    local child_uuid = session:getVariable('sip_h_X-child_intercept_uuid')
    if not child_uuid or child_uuid == '' then
        return false
    end

    local parent_uuid = channel_variable(api, child_uuid, 'ent_originate_aleg_uuid')
        or channel_variable(api, child_uuid, 'cc_member_session_uuid')
        or channel_variable(api, child_uuid, 'fifo_bridge_uuid')
        or child_uuid

    session:execute('intercept', parent_uuid)
    return true
end

if proxy_intercept() then
    return
end

if caller_extension == '' then
    freeswitch.consoleLog('WARNING', '[_group_pickup] missing caller extension\n')
    return
end

local dbh = freeswitch.Dbh('odbc://nizam')
if not dbh or not dbh:connected() then
    freeswitch.consoleLog('ERR', '[_group_pickup] unable to connect to database via ODBC DSN organization_runtime\n')
    return
end

local caller_ring_groups = {}
local escapedDomain = domain_name:gsub("'", "''")
local membershipQuery = string.format(
    "SELECT members FROM ring_groups rg INNER JOIN organizations o ON o.id = rg.organization_id WHERE o.domain = '%s' AND rg.is_active = 1",
    escapedDomain
)

assert(dbh:query(membershipQuery, function(row)
    local members = csv_split(row.members)
    for _, member in ipairs(members) do
        if member == caller_extension then
            for _, grouped in ipairs(members) do
                caller_ring_groups[grouped] = true
            end
            break
        end
    end
end))

dbh:release()

caller_ring_groups[caller_extension] = nil

local hasPeers = false
for _ in pairs(caller_ring_groups) do
    hasPeers = true
    break
end

if not hasPeers then
    freeswitch.consoleLog('INFO', string.format('[_group_pickup] no pickup peers found for %s\n', caller_extension))
    return
end

local allowed_directions = direction == 'outbound'
    and { inbound = true }
    or direction == 'all'
        and { inbound = true, outbound = true }
        or { outbound = true }

local command = "show channels as delim ||| uuid,direction,callstate,call_uuid,hostname,presence_id"
local rows = parse_api_rows(api:executeString(command))
local selected_uuid = nil
local selected_hostname = nil
local is_child = false

for _, row in ipairs(rows) do
    local presence = row.presence_id or ''
    local extension = presence:match('^(.-)@')
    if extension and caller_ring_groups[extension]
        and (row.state == 'RINGING' or row.state == 'EARLY')
        and allowed_directions[row.direction] then
        selected_uuid = row.uuid
        selected_hostname = row.hostname
        is_child = row.call_uuid ~= '' and row.uuid == row.call_uuid
        if row.call_uuid ~= '' then
            selected_uuid = row.call_uuid
        end
        break
    end
end

if not selected_uuid or selected_uuid == '' then
    freeswitch.consoleLog('INFO', string.format('[_group_pickup] no ringing group call found for %s\n', caller_extension))
    return
end

if is_child then
    local parent_uuid = channel_variable(api, selected_uuid, 'ent_originate_aleg_uuid')
        or channel_variable(api, selected_uuid, 'cc_member_session_uuid')
        or channel_variable(api, selected_uuid, 'fifo_bridge_uuid')
        or selected_uuid

    if selected_hostname and selected_hostname ~= '' and selected_hostname ~= hostname then
        session:execute('export', 'sip_h_X-child_intercept_uuid=' .. selected_uuid)
        make_proxy_call(pickup_number, selected_hostname)
        return
    end

    selected_uuid = parent_uuid
end

if selected_hostname and selected_hostname ~= '' and selected_hostname ~= hostname then
    session:execute('export', 'sip_h_X-intercept_uuid=' .. selected_uuid)
    make_proxy_call(pickup_number, selected_hostname)
    return
end

session:execute('intercept', selected_uuid)
