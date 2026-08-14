-- _menu.lua
-- Auto-attendant menu helper for organization call flows.
-- Arguments: <prompt> <timeout_seconds> <tries> <digit_map> <invalid_target> <timeout_target> <context>
--
-- digit_map is a comma separated list of digit:target pairs, e.g. "1:node_a,2:node_b".
--
-- The compiler emits this because the dialplan alone cannot collect a digit and
-- branch on it. Without it a menu node transferred straight to its timeout
-- branch, so every advertised option was unreachable.

local prompt = argv[1] or ''
local timeout = tonumber(argv[2]) or 5
local tries = tonumber(argv[3]) or 3
local digit_map_raw = argv[4] or ''
local invalid_target = argv[5]
local timeout_target = argv[6]
local context = argv[7]

if context == nil or context == '' then
    context = 'default'
end

-- Parse the digit map into digit -> target.
local targets = {}
local has_targets = false
for pair in string.gmatch(digit_map_raw, '[^,]+') do
    local digit, target = string.match(pair, '^%s*([^:]+)%s*:%s*(.+)%s*$')
    if digit and target then
        targets[digit] = target
        has_targets = true
    end
end

local function go(target, reason)
    if target and target ~= '' and target ~= 'null' then
        freeswitch.consoleLog('INFO', '[_menu] ' .. reason .. ' -> ' .. target .. '\n')
        session:execute('transfer', target .. ' XML ' .. context)
    else
        freeswitch.consoleLog('INFO', '[_menu] ' .. reason .. ', no target, hanging up\n')
        session:hangup()
    end
end

if not has_targets then
    -- Nothing to select between; treat as an immediate timeout so the graph's
    -- fallback still applies rather than silently dropping the caller.
    go(timeout_target or invalid_target, 'no digit targets configured')
    return
end

if not session:answered() then
    session:answer()
end

-- A menu has to be audible before digits mean anything. An empty prompt would
-- make play_and_get_digits return instantly and burn every retry.
if prompt == '' then
    prompt = 'silence_stream://500'
end

local collected_any_input = false

for attempt = 1, tries do
    -- min 1, max 1 digit, single try per call so retries are counted here and
    -- the "no input at all" case stays distinguishable from "wrong digit".
    local digits = session:playAndGetDigits(
        1, 1, 1,
        timeout * 1000,
        '#',
        prompt,
        '',
        '\\d'
    )

    if digits == nil or digits == '' then
        freeswitch.consoleLog('INFO', '[_menu] attempt ' .. attempt .. ': no input\n')
    else
        collected_any_input = true

        if targets[digits] then
            go(targets[digits], 'digit ' .. digits)
            return
        end

        freeswitch.consoleLog('INFO', '[_menu] attempt ' .. attempt .. ': unmapped digit ' .. digits .. '\n')
    end

    if not session:ready() then
        return
    end
end

-- Retries exhausted. A caller who never pressed anything timed out; one who
-- pressed the wrong keys gave invalid input. Fall back to whichever branch the
-- graph defines, preferring the one that matches what actually happened.
if collected_any_input then
    go(invalid_target or timeout_target, 'retries exhausted after invalid input')
else
    go(timeout_target or invalid_target, 'retries exhausted with no input')
end
