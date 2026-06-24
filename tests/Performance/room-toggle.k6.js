import http from 'k6/http';
import { check } from 'k6';

export const options = {
  thresholds: {
    http_req_duration: ['p(95)<1000'],
    http_req_failed: ['rate<0.01'],
  },
  scenarios: {
    roomToggle: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 5),
      duration: __ENV.DURATION || '30s',
    },
  },
};

const baseUrl = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const adminToken = __ENV.ADMIN_TOKEN;
const roomId = __ENV.ROOM_ID || 'imeet';
let loggedTimingSample = false;

export default function () {
  if (!adminToken) {
    throw new Error('ADMIN_TOKEN is required');
  }

  const active = __ITER % 2 === 0;
  const response = http.patch(
    `${baseUrl}/api/admin/rooms/${roomId}`,
    JSON.stringify({ is_active: active }),
    {
      headers: {
        Authorization: `Bearer ${adminToken}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    },
  );

  if (!loggedTimingSample) {
    loggedTimingSample = true;
    console.log(
      `timing sample: status=${response.status} duration_ms=${response.timings.duration.toFixed(2)} server_timing="${response.headers['Server-Timing'] || ''}"`,
    );
  }

  check(response, {
    'toggle returned 200': (res) => res.status === 200,
    'toggle under 1s': (res) => res.timings.duration < 1000,
  });
}
