const fs = require('fs');
const path = 'C:\\Users\\Usuario\\.gemini\\antigravity\\brain\\7799d1ba-88c1-4cc2-aa07-5301a92f586a\\.system_generated\\logs\\transcript.jsonl';
const lines = fs.readFileSync(path, 'utf8').split('\n');

for (const line of lines) {
    if (!line) continue;
    try {
        const obj = JSON.parse(line);
        if (obj.type === 'TOOL_CALL_OUTPUT' && obj.content && obj.content.includes('whatsapp.php')) {
            if (obj.content.includes('normalizeMessages')) {
                fs.writeFileSync('g:\\xampp\\htdocs\\NexusPanel-develop\\original_whatsapp_view.txt', obj.content);
                console.log("Saved original whatsapp.php view to original_whatsapp_view.txt");
                break;
            }
        }
    } catch(e) {}
}
