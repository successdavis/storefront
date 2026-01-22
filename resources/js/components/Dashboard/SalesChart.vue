<template>
    <v-chart class="chart" :option="chartOptions" />
</template>

<script>
import { defineComponent, watch, ref } from 'vue';
import VChart from 'vue-echarts';
import 'echarts';

export default defineComponent({
    components: { VChart },
    props: {
        data: Object // Ensure data is an object to avoid errors
    },
    setup(props) {
        const chartOptions = ref({
            title: {
                text: 'Sales Performance'
            },
            tooltip: {
                trigger: 'axis'
            },
            xAxis: {
                type: 'category',
                data: props.data.xAxis
            },
            yAxis: {
                type: 'value'
            },
            series: props.data.series
        });

        // Watch for changes in the data prop and update chartOptions
        watch(() => props.data, (newData) => {
            chartOptions.value = {
                title: {
                    text: 'Sales Performance'
                },
                tooltip: {
                    trigger: 'axis'
                },
                xAxis: {
                    type: 'category',
                    data: newData.xAxis
                },
                yAxis: {
                    type: 'value'
                },
                series: newData.series
            };
        }, { deep: true });

        return {
            chartOptions
        };
    }
});
</script>

<style>
.chart {
    width: 100%;
    height: 400px;
}
</style>
