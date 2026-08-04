/** Entry point for `bun run env:start`: a long-running Playground for interactive inspection. */
import { startPlayground } from './playground';

const server = await startPlayground();
console.log(`Playground running at ${server.serverUrl}`);
