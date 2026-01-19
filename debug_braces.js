const fs = require('fs');
const filePath = process.argv[2] || '/var/www/html/frontend/src/pages/documents/[id].vue';
const content = fs.readFileSync(filePath, 'utf8');

// Print all script tags
const linesRaw = content.split('\n');
for (let i = 0; i < linesRaw.length; i++) {
    if (linesRaw[i].includes('<script')) console.log(`Found <script at line ${i + 1}: ${linesRaw[i]}`);
    if (linesRaw[i].includes('</script>')) console.log(`Found </script> at line ${i + 1}: ${linesRaw[i]}`);
}

const startMatch = content.match(/<script.*?>/);
const endMatch = content.lastIndexOf('</script>');
const startIndex = startMatch.index + startMatch[0].length;
// Extract script part
const scriptContent = content.substring(startIndex, endMatch);
// ... rest of checking logic


let balance = 0;
let stack = [];
let inString = false;
let stringChar = '';
let inComment = false; // // style
let inMultiComment = false; // /* style

for (let i = 0; i < scriptContent.length; i++) {
    const char = scriptContent[i];
    const next = scriptContent[i + 1];

    if (inString) {
        if (char === '\\') { i++; continue; }
        if (char === stringChar) { inString = false; }
    } else if (inComment) {
        if (char === '\n') inComment = false;
    } else if (inMultiComment) {
        if (char === '*' && next === '/') { inMultiComment = false; i++; }
    } else {
        if (char === '/' && next === '/') { inComment = true; i++; }
        else if (char === '/' && next === '*') { inMultiComment = true; i++; }
        else if (char === '"' || char === '\'' || char === '`') {
            inString = true;
            stringChar = char;
        }
        else if (char === '{') {
            balance++;
            stack.push(i);
        } else if (char === '}') {
            balance--;
            stack.pop();
        }
    }
}

if (inString) console.log(`Error: Unclosed string starting with ${stringChar}`);
if (inMultiComment) console.log('Error: Unclosed multi-line comment');

if (balance !== 0) {
    console.log(`Unbalanced braces. Count: ${balance}`);
    if (balance > 0) {
        const idx = stack[stack.length - 1];
        console.log(`Last unclosed brace around index ${idx}`);
        const pre = scriptContent.substring(0, idx);
        const lineNo = pre.split('\n').length;
        // Adjust line number offset if needed (script start)
        const startLineOffset = content.substring(0, startIndex).split('\n').length;
        console.log(`Line number in file: ${lineNo + startLineOffset - 1}`);

        // Show context
        console.log('Context:', scriptContent.substring(idx, idx + 50));
    }
} else {
    console.log('Braces and strings seem balanced');
}
