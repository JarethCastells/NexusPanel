const fs = require('fs');
const readline = require('readline');

async function processTranscript() {
    const fileStream = fs.createReadStream('C:\\Users\\Usuario\\.gemini\\antigravity\\brain\\7799d1ba-88c1-4cc2-aa07-5301a92f586a\\.system_generated\\logs\\transcript.jsonl');

    const rl = readline.createInterface({
        input: fileStream,
        crlfDelay: Infinity
    });

    for await (const line of rl) {
        try {
            const parsed = JSON.parse(line);
            
            // Check tool calls
            if (parsed.tool_calls) {
                for (const tool of parsed.tool_calls) {
                    if (tool.tool_name === 'view_file' || tool.tool_name === 'multi_replace_file_content' || tool.tool_name === 'replace_file_content') {
                        const args = typeof tool.tool_arguments === 'string' ? JSON.parse(tool.tool_arguments) : tool.tool_arguments;
                        
                        if (args && args.AbsolutePath && args.AbsolutePath.includes('whatsapp-web-js.adapter.ts')) {
                            console.log(`Found whatsapp-web-js.adapter.ts in step ${parsed.step_index} with action ${tool.tool_name}`);
                        }
                        if (args && args.TargetFile && args.TargetFile.includes('whatsapp-web-js.adapter.ts')) {
                            console.log(`Found edit to whatsapp-web-js.adapter.ts in step ${parsed.step_index}`);
                        }
                    }
                }
            }
            
            // Check output responses
            if (parsed.type === 'TOOL_CALL_OUTPUT' && parsed.content && parsed.content.includes('whatsapp-web-js.adapter.ts')) {
                if (parsed.content.includes('Total Lines')) {
                   // This is a view_file output. Let's see if we can find fetchChatMessages
                   if (parsed.content.includes('fetchChatMessages')) {
                       console.log(`Viewed whatsapp-web-js.adapter.ts containing fetchChatMessages at step ${parsed.step_index}`);
                   }
                }
            }
        } catch (e) {
            // Ignore parse errors
        }
    }
}

processTranscript();
