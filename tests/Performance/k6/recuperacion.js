import recorrido, { umbrales } from './recorrido.js';
export const options = { noCookiesReset: true, scenarios: { recuperacion: { executor: 'constant-vus', vus: 1, duration: '1m' } },
    thresholds: umbrales, summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'] };
export default recorrido;
