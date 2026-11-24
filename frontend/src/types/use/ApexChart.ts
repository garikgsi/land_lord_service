export interface ApexSeriesContext {
  seriesIndex: number;
}

/** Options used by the project chart adapters (bundled ApexCharts 7). */
export interface ApexRadialOptions {
  chart: {
    type: 'radialBar';
    height: number;
    fontFamily: string;
    background: string;
    foreColor: string;
    parentHeightOffset: number;
    animations: { enabled: boolean };
    toolbar: { show: boolean };
  };
  series: number[];
  labels: string[];
  colors: string[];
  theme: { tokens: boolean };
  stroke: { lineCap: 'round' };
  fill: { type: 'solid' };
  plotOptions: {
    radialBar: {
      startAngle: number;
      endAngle: number;
      hollow: { size: string };
      track: { background: string; strokeWidth: string; margin: number };
      dataLabels: {
        name: { fontSize: string; color: string; offsetY: number; formatter: () => string };
        value: {
          fontSize: string;
          fontWeight: number;
          color: string;
          offsetY: number;
          formatter: () => string;
        };
        total: {
          show: boolean;
          label: string;
          color: string;
          fontSize: string;
          fontWeight: number;
          formatter: () => string;
        };
      };
    };
  };
  legend: {
    show: boolean;
    showForSingleSeries: boolean;
    position: 'bottom';
    horizontalAlign: 'left';
    fontSize: string;
    offsetX: number;
    offsetY: number;
    markers: { size: number };
    formatter: (name: string, context: ApexSeriesContext) => string;
    onItemClick: { toggleDataSeries: boolean };
    onItemHover: { highlightDataSeries: boolean };
  };
  tooltip: {
    enabled: boolean;
    fillSeriesColor: boolean;
    custom: (context: ApexSeriesContext) => HTMLElement;
  };
}

export interface ApexDonutOptions {
  chart: Omit<ApexRadialOptions['chart'], 'type'> & { type: 'donut' };
  series: number[];
  labels: string[];
  colors: string[];
  theme: ApexRadialOptions['theme'];
  stroke: { width: number; colors: string[] };
  dataLabels: { enabled: boolean };
  plotOptions: {
    pie: {
      expandOnClick: boolean;
      donut: {
        size: string;
        labels: {
          show: boolean;
          name: { show: boolean; fontSize: string; color: string };
          value: {
            show: boolean;
            fontSize: string;
            fontWeight: number;
            color: string;
            formatter: () => string;
          };
          total: {
            show: boolean;
            showAlways: boolean;
            label: string;
            color: string;
            fontSize: string;
            formatter: () => string;
          };
        };
      };
    };
  };
  legend: ApexRadialOptions['legend'];
  tooltip: {
    enabled: boolean;
    fillSeriesColor: boolean;
    y: { formatter: (value: number, context: ApexSeriesContext) => string };
  };
}

export type ApexChartOptions = ApexRadialOptions | ApexDonutOptions;

export interface ApexChartInstance {
  render(): Promise<void>;
  updateOptions(options: ApexChartOptions, redrawPaths?: boolean, animate?: boolean): Promise<void>;
  destroy(): void;
}

export interface ApexChartConstructor {
  new (element: HTMLElement, options: ApexChartOptions): ApexChartInstance;
}

declare global {
  interface Window {
    ApexCharts?: ApexChartConstructor;
  }
}
