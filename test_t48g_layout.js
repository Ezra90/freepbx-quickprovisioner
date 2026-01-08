#!/usr/bin/env node

/**
 * T48G Button Layout Integration Test
 * 
 * This test validates the complete T48G expandable layout implementation
 * by simulating the JavaScript logic and verifying the expected behavior.
 */

const fs = require('fs');
const path = require('path');

console.log('=== T48G Button Layout Integration Test ===\n');

// Load the T48G template
const templatePath = path.join(__dirname, 'templates', 'T48G.json');
const t48g = JSON.parse(fs.readFileSync(templatePath, 'utf8'));
const ve = t48g.visual_editor;

let testsPassed = 0;
let testsFailed = 0;

function test(name, condition, expected, actual) {
    if (condition) {
        console.log(`✓ ${name}`);
        testsPassed++;
    } else {
        console.log(`✗ ${name}`);
        console.log(`  Expected: ${expected}`);
        console.log(`  Actual: ${actual}`);
        testsFailed++;
    }
}

// Test Suite 1: Template Structure
console.log('Test Suite 1: Template Structure');
console.log('─────────────────────────────────');
test('Template has expandable_layout flag', 
     ve.expandable_layout === true, 
     'true', 
     ve.expandable_layout);

test('Template has svg_fallback flag', 
     ve.svg_fallback === true, 
     'true', 
     ve.svg_fallback);

test('Total keys count is 29', 
     ve.keys.length === 29, 
     '29', 
     ve.keys.length);

test('Max line keys is 29', 
     t48g.max_line_keys === 29, 
     '29', 
     t48g.max_line_keys);

console.log('');

// Test Suite 2: Column Layout
console.log('Test Suite 2: Column Layout');
console.log('─────────────────────────────');

const columnGroups = {};
ve.keys.forEach(key => {
    if (!columnGroups[key.column]) {
        columnGroups[key.column] = [];
    }
    columnGroups[key.column].push(key.index);
});

// Sort each column
for (let col in columnGroups) {
    columnGroups[col].sort((a, b) => a - b);
}

test('Column 1 has 6 keys', 
     columnGroups[1].length === 6, 
     '6', 
     columnGroups[1].length);

test('Column 1 contains keys 1-6', 
     columnGroups[1].join(',') === '1,2,3,4,5,6', 
     '[1,2,3,4,5,6]', 
     columnGroups[1]);

test('Column 2 has 6 keys', 
     columnGroups[2].length === 6, 
     '6', 
     columnGroups[2].length);

test('Column 2 contains keys 12-17', 
     columnGroups[2].join(',') === '12,13,14,15,16,17', 
     '[12,13,14,15,16,17]', 
     columnGroups[2]);

test('Column 3 has 6 keys', 
     columnGroups[3].length === 6, 
     '6', 
     columnGroups[3].length);

test('Column 3 contains keys 18-23', 
     columnGroups[3].join(',') === '18,19,20,21,22,23', 
     '[18,19,20,21,22,23]', 
     columnGroups[3]);

test('Column 4 has 6 keys', 
     columnGroups[4].length === 6, 
     '6', 
     columnGroups[4].length);

test('Column 4 contains keys 24-29', 
     columnGroups[4].join(',') === '24,25,26,27,28,29', 
     '[24,25,26,27,28,29]', 
     columnGroups[4]);

test('Column 5 has 5 keys', 
     columnGroups[5].length === 5, 
     '5', 
     columnGroups[5].length);

test('Column 5 contains keys 7-11', 
     columnGroups[5].join(',') === '7,8,9,10,11', 
     '[7,8,9,10,11]', 
     columnGroups[5]);

console.log('');

// Test Suite 3: Key Positions and Metadata
console.log('Test Suite 3: Key Positions and Metadata');
console.log('──────────────────────────────────────────');

const key1 = ve.keys.find(k => k.index === 1);
const key7 = ve.keys.find(k => k.index === 7);
const key29 = ve.keys.find(k => k.index === 29);

test('Key 1 is in column 1 (left edge)', 
     key1 && key1.column === 1, 
     'column 1', 
     key1 ? `column ${key1.column}` : 'not found');

test('Key 1 has correct x position (left edge)', 
     key1 && key1.x === 10, 
     '10', 
     key1 ? key1.x : 'not found');

test('Key 7 is in column 5 (right edge)', 
     key7 && key7.column === 5, 
     'column 5', 
     key7 ? `column ${key7.column}` : 'not found');

test('Key 7 has correct x position (right edge)', 
     key7 && key7.x === 650, 
     '650', 
     key7 ? key7.x : 'not found');

test('Key 29 is in column 4', 
     key29 && key29.column === 4, 
     'column 4', 
     key29 ? `column ${key29.column}` : 'not found');

test('No keys have page attribute', 
     ve.keys.every(k => k.page === undefined), 
     'no page attributes', 
     ve.keys.filter(k => k.page !== undefined).length + ' keys with page');

console.log('');

// Test Suite 4: Expandable Layout Logic Simulation
console.log('Test Suite 4: Expandable Layout Logic');
console.log('───────────────────────────────────────');

// Simulate collapsed view logic
function getVisibleKeysCollapsed() {
    return ve.keys.filter(key => key.column === 1 || key.index === 7);
}

// Simulate expanded view logic
function getVisibleKeysExpanded() {
    return ve.keys;
}

const collapsedKeys = getVisibleKeysCollapsed();
const collapsedIndices = collapsedKeys.map(k => k.index).sort((a, b) => a - b);

test('Collapsed view shows 7 keys', 
     collapsedKeys.length === 7, 
     '7', 
     collapsedKeys.length);

test('Collapsed view shows keys 1-6 and 7', 
     collapsedIndices.join(',') === '1,2,3,4,5,6,7', 
     '[1,2,3,4,5,6,7]', 
     collapsedIndices);

const expandedKeys = getVisibleKeysExpanded();

test('Expanded view shows 29 keys', 
     expandedKeys.length === 29, 
     '29', 
     expandedKeys.length);

test('Expanded view includes key 12', 
     expandedKeys.some(k => k.index === 12), 
     'true', 
     expandedKeys.some(k => k.index === 12));

test('Expanded view includes key 29', 
     expandedKeys.some(k => k.index === 29), 
     'true', 
     expandedKeys.some(k => k.index === 29));

console.log('');

// Test Suite 5: Screen Dimensions
console.log('Test Suite 5: Screen Dimensions');
console.log('─────────────────────────────────');

test('Screen width is 800px', 
     ve.screen_width === 800, 
     '800', 
     ve.screen_width);

test('Screen height is 480px', 
     ve.screen_height === 480, 
     '480', 
     ve.screen_height);

test('Chassis width is 800px', 
     ve.schematic.chassis_width === 800, 
     '800', 
     ve.schematic.chassis_width);

test('Chassis height is 480px', 
     ve.schematic.chassis_height === 480, 
     '480', 
     ve.schematic.chassis_height);

console.log('');

// Test Suite 6: Display Information
console.log('Test Suite 6: Display Information');
console.log('───────────────────────────────────');

test('Display name is "Yealink T48G"', 
     t48g.display_name === 'Yealink T48G', 
     'Yealink T48G', 
     t48g.display_name);

test('Model is "T48G"', 
     t48g.model === 'T48G', 
     'T48G', 
     t48g.model);

test('Manufacturer is "Yealink"', 
     t48g.manufacturer === 'Yealink', 
     'Yealink', 
     t48g.manufacturer);

console.log('');

// Final Results
console.log('═════════════════════════════════════════');
console.log(`Total Tests: ${testsPassed + testsFailed}`);
console.log(`Passed: ${testsPassed}`);
console.log(`Failed: ${testsFailed}`);
console.log('═════════════════════════════════════════');

if (testsFailed === 0) {
    console.log('\n✓ All tests passed! T48G layout implementation is correct.');
    process.exit(0);
} else {
    console.log(`\n✗ ${testsFailed} test(s) failed. Please review the implementation.`);
    process.exit(1);
}
