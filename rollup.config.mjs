import { readdirSync } from 'fs';
import { basename } from 'path';

const srcFiles = readdirSync('local/rangeos/amd/src').filter(f => f.endsWith('.js'));

// All Moodle-provided module namespaces — declared external so rollup
// leaves them as AMD dependencies rather than trying to bundle them.
const moodleExternal = (id) =>
    /^(core|mod|auth|local|block|theme|tool|report|gradereport|enrol|filter)\//.test(id);

export default srcFiles.map(file => ({
    input: `local/rangeos/amd/src/${file}`,
    external: moodleExternal,
    output: {
        file: `local/rangeos/amd/build/${basename(file, '.js')}.min.js`,
        format: 'amd',
        amd: { id: `local_rangeos/${basename(file, '.js')}` },
        sourcemap: true,
    },
}));
