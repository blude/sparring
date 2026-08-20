'use strict';

// Dynamic test generator — reads evals/sparring/scenarios/*.json verbatim (the same
// fixtures bin/eval_sparring.php uses) and turns each into one promptfoo test per scenario.
// Scenario content isn't duplicated anywhere in the promptfoo config; this is the only
// place that touches the files.

const fs = require('fs');
const path = require('path');

const SCENARIOS_DIR = path.join(__dirname, '..', 'scenarios');

module.exports = () => {
  const files = fs.readdirSync(SCENARIOS_DIR).filter((f) => f.endsWith('.json'));
  return files.map((file) => {
    const scenario = JSON.parse(fs.readFileSync(path.join(SCENARIOS_DIR, file), 'utf8'));
    return {
      description: scenario.id,
      // vars carries the whole scenario object — provider.js reads persona/directive/
      // opener/turns off context.vars directly.
      vars: scenario,
    };
  });
};
