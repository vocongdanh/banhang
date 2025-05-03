"use client";

import React from 'react';
import { 
  VictoryChart, VictoryLine, VictoryBar, VictoryPie, 
  VictoryTheme, VictoryAxis, VictoryTooltip, VictoryVoronoiContainer,
  VictoryArea, VictoryScatter, VictoryLabel
} from 'victory';

// Các theme có sẵn:
// 1. VictoryTheme.material (mặc định) - Material design theme
// 2. VictoryTheme.grayscale - Theme với màu xám
const availableThemes = {
  material: VictoryTheme.material,
  grayscale: VictoryTheme.grayscale,
  // Các theme tùy chỉnh
  light: {
    ...VictoryTheme.material,
    axis: {
      ...VictoryTheme.material.axis,
      style: {
        ...VictoryTheme.material.axis?.style,
        grid: {
          fill: "none",
          stroke: "#EEEEEE",
          strokeWidth: 1
        },
        tickLabels: {
          fill: "#333333",
          fontSize: 10
        },
        axis: {
          stroke: "#CCCCCC",
          strokeWidth: 1
        }
      }
    },
    line: {
      style: {
        data: {
          stroke: "#5569ff"
        },
        labels: {
          fill: "#333333",
          fontSize: 12
        }
      }
    }
  },
  dark: {
    ...VictoryTheme.material,
    axis: {
      ...VictoryTheme.material.axis,
      style: {
        ...VictoryTheme.material.axis?.style,
        grid: {
          fill: "none",
          stroke: "#444444",
          strokeWidth: 1
        },
        tickLabels: {
          fill: "#EEEEEE",
          fontSize: 10
        },
        axis: {
          stroke: "#777777",
          strokeWidth: 1
        }
      }
    },
    line: {
      style: {
        data: {
          stroke: "#7c8cfc"
        },
        labels: {
          fill: "#EEEEEE",
          fontSize: 12
        }
      }
    }
  }
};

interface ChartProps {
  type: 'line' | 'bar' | 'pie' | 'area' | 'scatter';
  data: Array<Record<string, any>>;
  x: string;
  y: string;
  title?: string;
  colors?: string[];
  theme?: keyof typeof availableThemes;
}

export default function ChartRenderer({ 
  type, 
  data, 
  x, 
  y, 
  title, 
  colors = ["#5569ff", "#42BF65", "#FFC000", "#E96E6E", "#8E8E8E"],
  theme = "light" // Sử dụng theme mặc định là "light"
}: ChartProps) {
  // Kiểm tra xem dữ liệu có tồn tại không
  if (!data || !Array.isArray(data) || data.length === 0) {
    return <div className="chart-error">Không có dữ liệu hoặc dữ liệu không đúng định dạng</div>;
  }

  // Chọn theme
  const selectedTheme = availableThemes[theme] || availableThemes.light;

  // Hàm parse dữ liệu để chuẩn bị cho biểu đồ
  const parseData = () => {
    return data.map(item => ({
      x: item[x],
      y: item[y],
      label: `${item[x]}: ${item[y]}`
    }));
  };

  const chartData = parseData();
  console.log("Chart data:", chartData);
  console.log("Chart type:", type);

  // Xử lý hiển thị các loại biểu đồ khác nhau
  switch (type) {
    case 'pie':
      return (
        <div className="chart-container" style={{ width: '100%', height: '400px' }}>
          {title && <h3 className="chart-title text-center font-semibold mb-3">{title}</h3>}
          <VictoryPie
            data={chartData}
            theme={selectedTheme}
            labelComponent={
              <VictoryLabel 
                style={{ fontSize: 12, fill: "#333" }}
              />
            }
            colorScale={colors}
            width={400}
            height={400}
            style={{
              data: { fillOpacity: 0.9, stroke: "white", strokeWidth: 2 },
              labels: { fontSize: 12 }
            }}
            padAngle={2}
            innerRadius={50}
          />
        </div>
      );
    
    case 'bar':
      return (
        <div className="chart-container" style={{ width: '100%', height: '400px' }}>
          {title && <h3 className="chart-title text-center font-semibold mb-3">{title}</h3>}
          <VictoryChart
            theme={selectedTheme}
            domainPadding={20}
            width={500}
            height={400}
          >
            <VictoryAxis />
            <VictoryAxis dependentAxis />
            <VictoryBar
              data={chartData}
              style={{
                data: { fill: colors[0], fillOpacity: 0.8 }
              }}
              labels={({ datum }: { datum: any }) => `${datum.label}`}
              labelComponent={
                <VictoryLabel 
                  dy={-15}
                  style={{ fontSize: 10, fill: "#333" }}
                />
              }
            />
          </VictoryChart>
        </div>
      );
    
    case 'area':
      return (
        <div className="chart-container" style={{ width: '100%', height: '400px' }}>
          {title && <h3 className="chart-title text-center font-semibold mb-3">{title}</h3>}
          <VictoryChart
            theme={selectedTheme}
            width={500}
            height={400}
          >
            <VictoryAxis />
            <VictoryAxis dependentAxis />
            <VictoryArea
              style={{
                data: { 
                  fill: colors[0], 
                  fillOpacity: 0.6,
                  stroke: colors[0],
                  strokeWidth: 2
                }
              }}
              data={chartData}
              interpolation="natural"
            />
            <VictoryScatter
              data={chartData}
              size={5}
              style={{
                data: { fill: colors[0] }
              }}
              labels={({ datum }: { datum: any }) => `${datum.label}`}
              labelComponent={
                <VictoryLabel 
                  dy={-15}
                  style={{ fontSize: 10, fill: "#333" }}
                />
              }
            />
          </VictoryChart>
        </div>
      );
      
    case 'scatter':
      return (
        <div className="chart-container" style={{ width: '100%', height: '400px' }}>
          {title && <h3 className="chart-title text-center font-semibold mb-3">{title}</h3>}
          <VictoryChart
            theme={selectedTheme}
            width={500}
            height={400}
          >
            <VictoryAxis />
            <VictoryAxis dependentAxis />
            <VictoryScatter
              style={{
                data: { 
                  fill: colors[0],
                  stroke: "white",
                  strokeWidth: 1
                }
              }}
              size={7}
              data={chartData}
              labels={({ datum }: { datum: any }) => `${datum.label}`}
              labelComponent={
                <VictoryLabel 
                  dy={-15}
                  style={{ fontSize: 10, fill: "#333" }}
                />
              }
            />
          </VictoryChart>
        </div>
      );
    
    case 'line':
    default:
      return (
        <div className="chart-container" style={{ width: '100%', height: '400px' }}>
          {title && <h3 className="chart-title text-center font-semibold mb-3">{title}</h3>}
          <VictoryChart
            theme={selectedTheme}
            width={500}
            height={400}
          >
            <VictoryAxis />
            <VictoryAxis dependentAxis />
            <VictoryLine
              style={{
                data: { stroke: colors[0], strokeWidth: 3 },
                parent: { border: "1px solid #ccc" }
              }}
              data={chartData}
              interpolation="natural"
            />
            <VictoryScatter
              data={chartData}
              size={5}
              style={{
                data: { fill: colors[0] }
              }}
              labels={({ datum }: { datum: any }) => `${datum.label}`}
              labelComponent={
                <VictoryLabel 
                  dy={-15}
                  style={{ fontSize: 10, fill: "#333" }}
                />
              }
            />
          </VictoryChart>
        </div>
      );
  }
} 