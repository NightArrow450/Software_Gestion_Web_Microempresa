import recorrido, { umbrales } from './recorrido.js';
export const options = { vus: 20, duration: '2m', noCookiesReset: true, thresholds: umbrales, summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'] };
export default recorrido;
