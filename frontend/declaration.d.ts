declare module 'victory' {
  import * as React from 'react';
  
  // Define core components
  export const VictoryChart: React.ComponentType<any>;
  export const VictoryLine: React.ComponentType<any>;
  export const VictoryBar: React.ComponentType<any>;
  export const VictoryPie: React.ComponentType<any>;
  export const VictoryAxis: React.ComponentType<any>;
  export const VictoryTooltip: React.ComponentType<any>;
  export const VictoryVoronoiContainer: React.ComponentType<any>;
  export const VictoryArea: React.ComponentType<any>;
  export const VictoryScatter: React.ComponentType<any>;
  export const VictoryLabel: React.ComponentType<any>;

  // Define themes
  export const VictoryTheme: {
    material: any;
    grayscale: any;
  };
}

declare module 'mermaid' {
  const mermaid: {
    initialize: (config: any) => void;
    run: (opts: any) => Promise<any>;
  };
  
  export default mermaid;
}

// Declare CSS modules
declare module '*.css' {
  const classes: { [key: string]: string };
  export default classes;
} 