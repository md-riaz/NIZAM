Good. Now we get into the heart of the system: how a call actually executes through the workflow. The schema you now have describes the graph. The execution engine is what walks that graph during a live call.
Think of it like a tiny operating system for calls. Each call is a process. Each node is an instruction.
A good execution model must satisfy four properties: deterministic behavior, resumability, external control (FreeSWITCH), and observability.
Let’s build that step by step.
1. Mental Model: Calls as State Machines
A running call is essentially a state machine moving through nodes.
Each step looks like:
Call arrives ↓ Execute node ↓ Node returns next transition ↓ Move to next node 
The node decides the next edge based on conditions.
So runtime logic becomes:
node = current_node handler = NodeHandlerFactory(node.type) result = handler.execute(context) next_node = edge(condition=result) 
This is the same pattern used in workflow engines like Temporal or BPMN runtimes.
2. Call Context Object
Every node must operate on the same shared context.
Example runtime object:
CallContext ----------- call_uuid tenant_id flow_id flow_version_id current_node_id caller_number destination_number variables 
Variables allow nodes to share data.
Example:
variables: digit_pressed = 2 customer_id = 8345 is_business_hours = true 
Context becomes the memory of the call.
3. Flow Execution Algorithm
This is the core engine.
Pseudo logic:
function execute(call_uuid): session = load_call_session(call_uuid) node = load_node(session.current_node) handler = NodeHandlerFactory.create(node.type) result = handler.execute(session.context) trace_event(node, result) next_node = resolve_edge(node, result.transition) update_session(next_node) continue_execution(next_node) 
Important detail: execution may pause.
Example:
When ringing a team, the system waits for an answer event.
4. Node Execution Contract
Every node handler should follow a simple contract.
Example interface:
interface NodeHandler { public function execute(CallContext $context): NodeResult; } 
Return object:
NodeResult ---------- transition payload wait_for_event 
Examples:
Menu node:
transition = digit_pressed 
Hours node:
transition = open 
Ring node:
wait_for_event = CALL_ANSWER 
5. Node Handlers Example
Business Hours Node
Logic:
Check schedule 
Handler:
if schedule.isOpen(now): return transition("open") else: return transition("closed") 
Edges:
open → menu closed → voicemail 
Menu Node
Flow:
Play audio Wait for digit 
Handler:
play_audio(prompt) digit = wait_for_dtmf() return transition("digit_"+digit) 
Edges:
digit_1 → sales digit_2 → support timeout → voicemail 
Ring Team Node
Flow:
Ring endpoints Wait for answer 
Handler:
originate_call(team_members) wait_for_event(CHANNEL_ANSWER) 
Possible transitions:
answered timeout busy 
6. Event Driven Continuation
Some nodes pause execution.
Example:
Ring team 
When answer happens, FreeSWITCH emits:
CHANNEL_ANSWER 
Your ESL listener receives it.
Then runtime resumes.
EventProcessor → FlowExecutionService.resume(call_uuid) 
Resume logic:
current_node = ring_node transition = answered next_node = edge(answered) 
7. Node Edge Resolution
Edges determine next step.
Example DB record:
source_node = menu condition = digit_1 target_node = sales_ring 
Resolver:
function resolve_edge(node, transition): edges = load_edges(node) return edges.where(condition == transition) 
Fallback edge:
condition = default 
8. Flow Execution Modes
There are two execution patterns.
Immediate nodes
Executed instantly.
Examples:
hours_check variable_set webhook 
Async nodes
Require events.
Examples:
menu ring queue recording 
Async nodes pause the engine.
9. Trace Recording
Every node execution should log.
Example record:
call_uuid node_id node_type action timestamp payload
Example trace:
start hours_check(open) menu digit_1 ring_team answered hangup 
Later this becomes call debugging UI.
10. FreeSWITCH Interaction
Your execution engine must instruct FreeSWITCH.
Example commands:
playback bridge hangup transfer 
Example:
uuid_broadcast uuid_bridge uuid_kill 
Handlers translate node actions into FreeSWITCH commands.
11. Failure Handling
Never allow execution to crash.
Fallback rule:
node error → hangup 
or
node error → voicemail 
Store error in trace log.
12. Scaling Model
Execution is stateless except for call_session.
Multiple workers can run the engine.
API Server │ Redis Queue │ Flow Workers 
Workers process:
call start call events node execution 
This allows horizontal scaling.
13. Advanced Feature Later
Once the engine works you can add powerful nodes.
Examples:
HTTP Decision Node
call webhook route based on response 
Example:
VIP customer → account manager 
AI Node
speech to intent 
Example:
caller says "billing" 
Data Lookup Node
lookup CRM 
Flow:
VIP → priority queue 
14. Visual Builder Mapping
Your UI builder eventually creates this JSON.
Example flow definition:
{ "nodes":[ {"id":"start","type":"start"}, {"id":"hours","type":"business_hours"}, {"id":"menu","type":"menu"}, {"id":"sales","type":"ring_team"} ], "edges":[ {"from":"start","to":"hours"}, {"from":"hours","condition":"open","to":"menu"}, {"from":"hours","condition":"closed","to":"voicemail"}, {"from":"menu","condition":"digit_1","to":"sales"} ] } 
Saved into:
flow_versions.definition_json 
15. The Key Insight
The PBX world traditionally uses dialplans.
Modern systems use workflow execution engines.
Your project becomes:
Telephony + Workflow Engine 
That combination is extremely powerful.
Final perspective
If you implement:
• versioned flows
• node handlers
• event driven execution
• trace logging
you will have built something much closer to Twilio Studio’s runtime than to a traditional PBX.
And interestingly, most open PBX systems still lack this architecture, which means you're building something genuinely modern.
If you'd like, I can also show you the biggest architectural mistake most new voice platforms make (and how to avoid it in NIZAM). It will save you from a painful rewrite later.