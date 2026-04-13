-- nizam_valet_park.lua
-- Auto-select an open valet parking orbit and park the current call.
-- Arguments: <lot> <orbit_entry_extension> <orbit_start> <orbit_end>

local lot = argv[1] or 'park'
local orbit_entry_extension = argv[2] or '*5900'
local orbit_start = tonumber(argv[3]) or 5901
local orbit_end = tonumber(argv[4]) or 5999

local function trim(value)
    if not value then
        return ''
    end

    return (value:gsub('^%s+', ''):gsub('%s+$', ''))
end

local api = freeswitch.API()

if not session:ready() then
    return
end

local context = session:getVariable('context') or session:getVariable('domain_name') or 'default'
local caller_id_number = session:getVariable('caller_id_number') or ''
local displayEnabled = (session:getVariable('valet_parking_display') or '') == 'enable'
local announceSlot = (session:getVariable('valet_announce_slot') or '') == 'enable'
local uuid = session:getVariable('uuid') or ''

local info = trim(api:executeString('valet_info ' .. lot .. '@' .. context))
local destination_number = nil

for orbit = orbit_start, orbit_end do
    if not info:find('%*' .. tostring(orbit), 1, true) then
        destination_number = orbit
        break
    end
end

if not destination_number then
    freeswitch.consoleLog('ERR', string.format('[nizam_valet_park] no open parking orbit in %s@%s (%d-%d)\n', lot, context, orbit_start, orbit_end))
    session:execute('respond', '486')
    return
end

freeswitch.consoleLog('NOTICE', string.format('[nizam_valet_park] %s@%s parked in *%d via %s\n', caller_id_number, context, destination_number, orbit_entry_extension))

if displayEnabled and uuid ~= '' then
    api:executeString("uuid_display " .. uuid .. " 'parked in *" .. destination_number .. "'")
    session:execute('sleep', '3000')
end

if announceSlot then
    session:execute('say', 'en name_spelled iterated *' .. tostring(destination_number))
end

session:execute('valet_park', lot .. '@' .. context .. ' *' .. tostring(destination_number))
