import recorrido from './recorrido.js';
export const options = { noCookiesReset: true, scenarios: { estres: { executor: 'ramping-vus', startVUs: 0, stages: [{ duration: '30s', target: 10 }, { duration: '30s', target: 20 }, { duration: '30s', target: 35 },
    { duration: '1m', target: 50 }, { duration: '30s', target: 1 }, { duration: '1m', target: 1 }, { duration: '10s', target: 0 }] } },
    thresholds: { checks: ['rate==1'] }, summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'max'] };
export default recorrido;
