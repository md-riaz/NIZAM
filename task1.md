Excellent. Designing the flow engine schema correctly is the most important architectural decision you’ll make in this project. If the schema is wrong, every feature later becomes painful. If it’s right, the system evolves naturally.
The trick is to model calls as a workflow graph executed over time.
A graph has nodes and edges. A call moves through that graph.
So the database should store:
• flows
• versions
• nodes
• connections
• runtime call state
Let’s build this carefully.
1. Core Concept
A Flow represents behavior for a number.
Example:
Incoming call ↓ Business hours? ↓ Menu ↓ Ring sales team 
Important: flows must be versioned.
You cannot modify a flow while calls are running. Instead you publish a new version.
So we separate:
Flow FlowVersion 
2. Flows Table
Stores the logical workflow container.
flows ----- id (uuid) tenant_id name description active_version_id created_at updated_at 
Example record:
id: flow-123 name: Main Office Routing active_version_id: version-8 
This allows safe deployment of new routing.
3. Flow Versions
Every time a flow changes, a new version is created.
flow_versions ------------- id (uuid) flow_id version_number definition_checksum is_published created_at 
Example:
flow_id: flow-123 version: 8 
A call always executes against one immutable version.
4. Flow Nodes
Each node represents an action.
flow_nodes ---------- id (uuid) flow_version_id type name config_json position_x position_y created_at 
Example:
type: menu name: Main Menu 
Config JSON:
{ "prompt": "welcome.wav", "timeout": 5 } 
Position fields help UI draw the graph.
5. Flow Edges
Edges define transitions between nodes.
flow_edges ---------- id (uuid) flow_version_id source_node_id target_node_id condition created_at 
Example:
source: menu_node target: sales_ring condition: digit=1 
Another:
condition: timeout 
6. Node Types
Node types define behavior.
Recommended starting set:
start business_hours holiday_check menu ring_user ring_team queue voicemail webhook play_audio hangup 
Each node type has a handler class.
Example:
app/Flow/Nodes/MenuNodeHandler app/Flow/Nodes/RingTeamHandler app/Flow/Nodes/HoursCheckHandler 
7. Runtime Call Sessions
Calls need runtime state.
call_sessions ------------- id (uuid) tenant_id flow_id flow_version_id current_node_id call_uuid state started_at ended_at 
Example:
call_uuid: freeswitch_uuid current_node: menu_node 
This allows pause/resume logic.
8. Call Trace Events
This table becomes your debugging superpower.
call_trace_events ----------------- id call_session_id node_id node_type action payload_json created_at 
Example:
node: business_hours action: matched timestamp: 14:03:12 
Later you can show a visual call replay.
Competitors often lack this.
9. Numbers Table
Numbers map to flows.
numbers ------- id tenant_id phone_number flow_id is_active created_at 
Example:
+88012345678 → flow: Main Office 
10. Schedules
Schedules control time routing.
schedules --------- id tenant_id name timezone schedule_rules -------------- id schedule_id day_of_week start_time end_time 
Example:
Mon 09:00 → 18:00 
11. Holidays
holidays -------- id tenant_id name date 
Example:
Eid 2026-03-21 
Your HolidayCheck node uses this.
12. Teams
Teams represent groups of endpoints.
teams ----- id tenant_id name strategy timeout 
Example:
strategy: simultaneous 
13. Team Members
team_members ------------ id team_id endpoint_type endpoint_id priority 
Example:
endpoint: extension 
14. Extensions
You already have this.
extensions ---------- id tenant_id extension password sip_username 
15. Voicemail Boxes
voicemail_boxes --------------- id tenant_id mailbox pin email 
16. Webhooks
For automation.
webhooks -------- id tenant_id url secret subscribed_events 
Example events:
call.started call.answered call.hangup voicemail.received
17. Execution Flow Example
Example runtime.
Incoming call:
+88012345678 
System resolves:
number → flow 
Then execution:
start node ↓ hours check ↓ menu ↓ ring team 
During execution we write events:
call_trace_events 
Example trace:
START HOURS_MATCH MENU_DIGIT_1 TEAM_RING ANSWER HANGUP 
This creates a full call history.
18. Why this schema works
This design gives you:
• versioned workflows
• safe deployments
• node based routing
• call debugging
• horizontal scalability
And most importantly:
The UI becomes just a visual editor for flows.
19. Future power features
Once this foundation exists, you can add:
AI nodes
analyze caller intent 
CRM lookup
lookup customer → route VIP 
callback automation
SMS nodes
call recording analysis
All without redesigning the system.
Final thought
Your instinct to remove the half-built UI and focus on the backend architecture first is exactly what experienced platform engineers do.
The moment the flow engine is solid, the rest of the system becomes dramatically easier.
If you'd like, I can also show you the exact flow execution algorithm used by modern systems like Twilio Studio, which will make implementing the runtime much simpler.