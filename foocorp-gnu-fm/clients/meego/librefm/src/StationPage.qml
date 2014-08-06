import QtQuick 1.1
import com.nokia.meego 1.0

Page {
    id: stationPage
    anchors.margins: rootWin.pageMargin
    tools: commonTools

    Connections {
        target: serverComm
        onTuned: {
            lblStationName.text = stationName;
            lblArtist.text = "Fetching playlist...";
        }

        onPlaying: {
            lblArtist.text = artist;
            lblSpacer.text = " - ";
            lblTrack.text = title;
            imgCover.source = imageurl;
        }

        onPositionUpdate: {
            songProgress.value = position;
        }

        onNoContent: {
            pause();
            msg_no_content.open();
        }

    }

    states: [
        State {
            name: "inLandscape"
            when: !rootWin.inPortrait
            PropertyChanges {
                target: grid_details
                rows: 1
                columns: 2
            }
            PropertyChanges {
                target: col_details
                anchors.verticalCenterOffset: 0
            }
            PropertyChanges {
                target: imgCover
                anchors.horizontalCenterOffset: -250
            }
        },
        State {
            name: "inPortrait"
            when: rootWin.inPortrait
            PropertyChanges {
                target: grid_details
                rows: 2
                columns: 1
            }
            PropertyChanges {
                target: col_details
                anchors.verticalCenterOffset: 100
            }
            PropertyChanges {
                target: imgCover
                anchors.horizontalCenterOffset: 0
            }
        }
    ]

    Column {
        anchors.horizontalCenter: parent.horizontalCenter
        spacing: 35

        Image {
            id: imgLibre
            source: "librefm-logo.png"
            anchors.horizontalCenter: parent.horizontalCenter
            z: -1
            visible: false
        }

        Label {
            id: lblStationName
            text: " "
            anchors.horizontalCenter: parent.horizontalCenter
            anchors.verticalCenterOffset: 5
            font.weight: Font.Bold
            font.pixelSize: 30
        }

        Grid {
            id: grid_details
            spacing: 50
            anchors.horizontalCenter: parent.horizontalCenter
            Image {                
                id: imgCover
                anchors.horizontalCenter: parent.horizontalCenter
                source: "empty-album.png"
                height: 200
                width: 200
            }

            Column {
                id: col_details
                spacing: 40
                anchors.verticalCenter: parent.verticalCenter
                Row {
                    anchors.horizontalCenter: parent.horizontalCenter
                    Label {
                        id: lblArtist
                        text: "Tuning in..."
                    }
                    Label {
                        id: lblSpacer
                    }
                    Label {
                        id: lblTrack
                    }
                }

                Slider {
                    id: songProgress
                    value: 0
                }
            }

        }

    }

    ButtonRow {
        exclusive: false
        anchors.bottom: parent.bottom

        Button {
            id: btnBan
            Image {
                anchors.centerIn: parent
                anchors.verticalCenterOffset: -1
                source: "ban.png"
            }
            onClicked: {
                rootWin.ban();
                rootWin.next();
            }
        }

        Button {
            id: btnTag
            Image {
                anchors.centerIn: parent
                source: "image://theme/icon-m-toolbar-tag" + (theme.inverted ? "-inverse" : "")
            }
        }

        Button {
            id: btnPrevious
            Image {
                anchors.centerIn: parent
                source: "image://theme/icon-m-toolbar-mediacontrol-previous" + (theme.inverted ? "-inverse" : "")
            }
            onClicked: {
                rootWin.prev();
            }
        }

        Button {
            id: btnPlay
            property bool playing: false;
            Image {
                id: imgPlay
                anchors.centerIn: parent
                visible: false
                source: "image://theme/icon-m-toolbar-mediacontrol-play" + (theme.inverted ? "-inverse" : "")
            }

            Image {
                id: imgPause
                anchors.centerIn: parent
                source: "image://theme/icon-m-toolbar-mediacontrol-pause" + (theme.inverted ? "-inverse" : "")
            }

            onClicked: {
                if (imgPlay.visible) {
                    rootWin.play();
                    imgPlay.visible = false;
                    imgPause.visible = true;
                } else {
                    rootWin.pause();
                    imgPlay.visible = true;
                    imgPause.visible = false;
                }
            }


        }

        Button {
            id: btnNext
            Image {
                anchors.centerIn: parent
                source: "image://theme/icon-m-toolbar-mediacontrol-next" + (theme.inverted ? "-inverse" : "")
            }
            onClicked: {
                rootWin.next();
            }
        }

        Button {
            id: btnSave
            Image {
                anchors.centerIn: parent
                source: "image://theme/icon-m-common-save-as" + (theme.inverted ? "-inverse" : "")
                scale: 0.8
            }
        }

        Button {
            id: btnLove
            Image {
                anchors.centerIn: parent
                anchors.verticalCenterOffset: -1
                source: "love.png"
            }
            onClicked: {
                rootWin.love();
            }
        }

    }


    QueryDialog {
        id: msg_no_content
        titleText: "No more content"
        message: "This station doesn't appear to have any more content. If this is one of your loved, mix, recommendation or neighbourhood stations then you may need to love a few more songs first."
        rejectButtonText: "Okay"
        onAccepted: pageStack.pop();
        onRejected: pageStack.pop();
    }

}
