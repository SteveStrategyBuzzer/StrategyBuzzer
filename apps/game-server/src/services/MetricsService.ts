export type LatencyHistogram = {
  values: number[];
  count: number;
  sum: number;
};

export type MetricsData = {
  activeRooms: number;
  totalPlayers: number;
  roomsByPhase: Record<string, number>;
  buzzLatency: { avg: number; p95: number; p99: number; count: number };
  answerLatency: { avg: number; p95: number; p99: number; count: number };
  eventsReceived: Record<string, number>;
  eventsProcessed: number;
  eventsFailed: number;
  errors: {
    validation: Record<string, number>;
    roomNotFound: number;
    auth: number;
  };
  uptime: number;
  timestamp: number;
};

class MetricsServiceImpl {
  private static instance: MetricsServiceImpl;

  private buzzLatencies: number[] = [];
  private answerLatencies: number[] = [];
  private eventsReceived: Record<string, number> = {};
  private eventsProcessed = 0;
  private eventsFailed = 0;
  private validationErrors: Record<string, number> = {};
  private roomNotFoundErrors = 0;
  private authErrors = 0;
  private startTime = Date.now();

  private readonly MAX_LATENCY_SAMPLES = 1000;

  private constructor() {}

  static getInstance(): MetricsServiceImpl {
    if (!MetricsServiceImpl.instance) {
      MetricsServiceImpl.instance = new MetricsServiceImpl();
    }
    return MetricsServiceImpl.instance;
  }

  recordBuzzLatency(latencyMs: number): void {
    this.buzzLatencies.push(latencyMs);
    if (this.buzzLatencies.length > this.MAX_LATENCY_SAMPLES) {
      this.buzzLatencies.shift();
    }
  }

  recordAnswerLatency(latencyMs: number): void {
    this.answerLatencies.push(latencyMs);
    if (this.answerLatencies.length > this.MAX_LATENCY_SAMPLES) {
      this.answerLatencies.shift();
    }
  }

  incrementEventReceived(eventType: string): void {
    this.eventsReceived[eventType] = (this.eventsReceived[eventType] || 0) + 1;
  }

  incrementEventsProcessed(): void {
    this.eventsProcessed++;
  }

  incrementEventsFailed(): void {
    this.eventsFailed++;
  }

  incrementValidationError(eventType: string): void {
    this.validationErrors[eventType] = (this.validationErrors[eventType] || 0) + 1;
  }

  incrementRoomNotFoundError(): void {
    this.roomNotFoundErrors++;
  }

  incrementAuthError(): void {
    this.authErrors++;
  }

  private calculatePercentile(values: number[], percentile: number): number {
    if (values.length === 0) return 0;
    const sorted = [...values].sort((a, b) => a - b);
    const index = Math.ceil((percentile / 100) * sorted.length) - 1;
    return sorted[Math.max(0, index)];
  }

  private calculateLatencyStats(values: number[]): { avg: number; p95: number; p99: number; count: number } {
    if (values.length === 0) {
      return { avg: 0, p95: 0, p99: 0, count: 0 };
    }
    const sum = values.reduce((a, b) => a + b, 0);
    return {
      avg: Math.round(sum / values.length),
      p95: Math.round(this.calculatePercentile(values, 95)),
      p99: Math.round(this.calculatePercentile(values, 99)),
      count: values.length,
    };
  }

  getMetrics(activeRooms: number, totalPlayers: number, roomsByPhase: Record<string, number>): MetricsData {
    return {
      activeRooms,
      totalPlayers,
      roomsByPhase,
      buzzLatency: this.calculateLatencyStats(this.buzzLatencies),
      answerLatency: this.calculateLatencyStats(this.answerLatencies),
      eventsReceived: { ...this.eventsReceived },
      eventsProcessed: this.eventsProcessed,
      eventsFailed: this.eventsFailed,
      errors: {
        validation: { ...this.validationErrors },
        roomNotFound: this.roomNotFoundErrors,
        auth: this.authErrors,
      },
      uptime: Math.floor((Date.now() - this.startTime) / 1000),
      timestamp: Date.now(),
    };
  }

  reset(): void {
    this.buzzLatencies = [];
    this.answerLatencies = [];
    this.eventsReceived = {};
    this.eventsProcessed = 0;
    this.eventsFailed = 0;
    this.validationErrors = {};
    this.roomNotFoundErrors = 0;
    this.authErrors = 0;
  }
}

export const MetricsService = MetricsServiceImpl.getInstance();
