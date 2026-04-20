-- _team_ring.lua
-- Team routing helper for organization call flows
-- Arguments: <dial_string> <timeout> <answered_target> <no_answer_target> <timeout_target>

local dial_string = argv[1]
local timeout = tonumber(argv[2]) or 30
local answered_target = argv[3]
local no_answer_target = argv[4]
local timeout_target = argv[5]

if not dial_string or dial_string == "" then
    freeswitch.consoleLog("WARNING", "[_team_ring] Empty dial string, treating as no_answer\n")
    session:execute("transfer", no_answer_target .. " XML default")
    return
end

-- Set call timeout
session:execute("set", "call_timeout=" .. tostring(timeout))
session:execute("set", "hangup_after_bridge=true")
session:execute("set", "continue_on_fail=true")

freeswitch.consoleLog("INFO", "[_team_ring] Bridging to team: " .. dial_string .. "\n")

-- Execute the bridge
session:execute("bridge", dial_string)

-- If we are still here, the call was not bridged successfully (not answered) or the bridged call hung up.
-- If it was answered and hangup_after_bridge=true, the session would be terminated.
-- But we can check originate_disposition just in case.
local disposition = session:getVariable("originate_disposition") or ""
freeswitch.consoleLog("INFO", "[_team_ring] Bridge disposition: " .. disposition .. "\n")

if disposition == "USER_BUSY" or disposition == "NO_ANSWER" or disposition == "USER_NOT_REGISTERED" or disposition == "UNALLOCATED_NUMBER" then
    session:execute("transfer", no_answer_target .. " XML default")
elseif disposition == "NO_USER_RESPONSE" or disposition == "TIMEOUT" then
    session:execute("transfer", timeout_target .. " XML default")
elseif disposition == "SUCCESS" then
    -- It was answered but the A-leg didn't hang up? (shouldn't happen with hangup_after_bridge)
    if answered_target and answered_target ~= "" and answered_target ~= "null" then
        session:execute("transfer", answered_target .. " XML default")
    end
else
    -- Default fallback for other failure cases
    session:execute("transfer", no_answer_target .. " XML default")
end
