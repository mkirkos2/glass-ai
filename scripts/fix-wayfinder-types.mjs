import { readFileSync, writeFileSync } from 'node:fs';

const file = new URL('../resources/js/wayfinder/index.ts', import.meta.url);

const source = readFileSync(file, 'utf8');

const methodPattern = /^type Method = (.*);$/m;
const match = source.match(methodPattern);

if (!match) {
    throw new Error('Wayfinder Method type was not found.');
}

if (match[1].includes('"query"')) {
    console.log('Wayfinder Method already supports query.');
} else {
    const updated = source.replace(
        methodPattern,
        (_, methods) => `type Method = ${methods} | "query";`,
    );

    writeFileSync(file, updated, 'utf8');

    console.log('Added query to the Wayfinder Method type.');
}
