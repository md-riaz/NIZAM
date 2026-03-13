There is a trap waiting for almost every engineer building a voice platform. It doesn’t appear immediately. The system works fine for the first few thousand calls. Then one day everything becomes messy, unpredictable, and hard to debug. Many PBX and telecom platforms suffer from this.
The mistake is simple to describe:
Mixing telephony events with workflow execution logic.
Let’s unpack that carefully.
In a voice system two very different things are happening at the same time.
One side is the media engine. FreeSWITCH is generating a firehose of events:
CHANNEL_CREATE
CHANNEL_ANSWER
CHANNEL_BRIDGE
CHANNEL_HANGUP
DTMF
RECORD_START
RECORD_STOP
These are low-level telephony signals. They describe what the SIP/media layer is doing.
The other side is the business workflow. Your flow engine is thinking about:
Menu digit pressed
Business hours matched
Team answered
Voicemail started
These are high-level product events.
The mistake many systems make is letting these two layers talk to each other directly. A node handler listens to raw ESL events and decides what to do next. At first it seems convenient. Eventually it becomes chaos.
Why?
Because telephony events are noisy and unpredictable.
A single call might generate:
20+ channel state events
multiple DTMF events
early media events
bridge attempts
retries
If workflow nodes start reacting directly to those, the system becomes fragile. A tiny race condition can send the call down the wrong branch.
Modern communication platforms solve this by inserting a translation layer.
Think of it as a biological nervous system.
FreeSWITCH is the sensory organs.
The event normalizer is the spinal cord.
The workflow engine is the brain.
The spinal cord translates raw signals into meaningful events.
Example.
FreeSWITCH produces this:
CHANNEL_ANSWER
Your platform converts it into a domain event:
call.answered
Another example:
DTMF digit 1
Becomes:
menu.selection = 1
Once this translation happens, the workflow engine never sees raw telephony events. It only receives domain events.
That separation changes everything.
Now your workflow engine is deterministic. A Menu node waits for exactly one thing: menu.selection. A Ring node waits for call.answered or call.timeout. The logic becomes clear and testable.
So the architecture becomes three layers.
At the bottom, the media runtime.
FreeSWITCH handles SIP, RTP, codecs, and bridging. It emits events through ESL.
Above that sits the event normalizer. This component listens to ESL and converts events into platform events. It may also enrich them with context like tenant ID or call session ID.
The normalized events are pushed into your system through Redis streams or an internal event bus.
At the top sits the workflow engine. It consumes those normalized events and advances the call state machine. It never interacts directly with FreeSWITCH.
This design has several benefits that are not obvious at first.
First, debugging becomes dramatically easier. You can inspect a call’s domain events and understand exactly what happened without reading telephony logs.
Second, the workflow engine becomes portable. If you ever wanted to run a different media backend—maybe WebRTC infrastructure or a cloud SIP provider—the workflow engine would not need to change.
Third, scaling becomes straightforward. Event processors and flow workers can run independently.
There’s another mistake that often accompanies the first one: letting nodes directly call FreeSWITCH commands everywhere. That creates hidden coupling.
A better pattern is to have a small media control service responsible for commands like playback, bridge, transfer, or hangup. Nodes request actions from that service rather than issuing commands themselves.
Then your architecture starts to look clean and layered.

FreeSWITCH produces events.
The normalizer converts them.
The event bus distributes them.
Flow workers execute the workflow.
The media controller performs telephony actions.
What looks at first like extra complexity actually produces a much calmer system. The chaos of SIP and RTP stays contained at the bottom. The product logic remains simple and predictable.
There is a philosophical lesson hidden here. Many PBX systems try to be both the media engine and the application platform. That leads to tangled designs. Modern platforms treat the media layer as infrastructure and build the product logic above it.
If NIZAM follows that discipline—clear event translation, strict separation between telephony and workflow logic—it will avoid the trap that has made many telecom systems difficult to evolve.
And once that separation exists, something interesting happens: calls stop looking like telephony problems and start looking like data flowing through a distributed workflow system. That shift opens the door to features far beyond traditional PBX behavior.