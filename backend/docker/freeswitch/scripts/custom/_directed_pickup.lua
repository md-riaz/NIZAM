-- _directed_pickup.lua
-- Directed call pickup helper for platform-native intercept behavior.
-- Arguments: <extension> [direction]

local extension = argv[1]
local direction = argv[2] or (extension and 'inbound' or 'all')
local pickup_number = '*8'

local function trim(value)
    if not value then
        return ''
    end

    return (value:gsub('^%s+', ''):gsub('%s+$', ''))
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

local function parse_api_rows(raw)
    local rows = {}
    raw = trim(raw)
    if raw == '' or raw == '-ERR no reply' then
        return rows
    end

    for line in string.gmatch(raw, '[^\r\n]+') do
        if line ~= '' and not line:match('^%+OK') then
            local uuid, directionValue, state, callUuid, hostname, presenceId = line:match('^(.-)|(.-)|(.-)|(.-)|(.-)|(.+)$')
            if uuid then
                rows[#rows + 1] = {
                    uuid = trim(uuid),
                    direction = trim(directionValue),
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

local api = freeswitch.API()

if not session:ready() then
    return
end

session:answer()

local domain_name = session:getVariable('domain_name') or session:getVariable('context') or 'default'
local hostname = trim(api:executeString('hostname'))

local function make_proxy_call(destination, call_hostname)
    local target = destination .. '@' .. domain_name
    local dial_string = string.format('{sip_invite_domain=%s}sofia/internal/%s;fs_path=sip:%s', domain_name, target, call_hostname)
    freeswitch.consoleLog('INFO', string.format('[_directed_pickup] proxying pickup %s to %s\n', destination, call_hostname))
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

if not extension or extension == '' then
    freeswitch.consoleLog('WARNING', '[_directed_pickup] missing extension argument\n')
    return
end

local direction_filter = {
    inbound = { outbound = true },
    outbound = { inbound = true },
    all = { inbound = true, outbound = true },
}
local allowed_directions = direction_filter[direction] or direction_filter.inbound
local command = "show channels as delim ||| uuid,direction,callstate,call_uuid,hostname,presence_id"
local rows = parse_api_rows(api:executeString(command))

local selected_uuid = nil
local selected_hostname = nil
local is_child = false

for _, row in ipairs(rows) do
    if (row.state == 'RINGING' or row.state == 'EARLY')
        and allowed_directions[row.direction]
        and row.presence_id == extension .. '@' .. domain_name then
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
    freeswitch.consoleLog('INFO', string.format('[_directed_pickup] no ringing call found for %s\n', extension))
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
