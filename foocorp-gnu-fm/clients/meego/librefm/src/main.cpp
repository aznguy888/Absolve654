#include <QApplication>
#include <QDeclarativeContext>
#include <QDeclarativeView>
#include <QDebug>
#include <iostream>
#include "servercomm.h"


void msgHandler( QtMsgType type, const char* msg )
{
    const char symbols[] = { 'I', 'E', '!', 'X' };
    QString output = QString("[%1] %2").arg( symbols[type] ).arg( msg );
    std::cerr << output.toStdString() << std::endl;
    if( type == QtFatalMsg ) abort();
}


int main(int argc, char *argv[])
{
    qInstallMsgHandler( msgHandler );
    QApplication app(argc, argv);
    app.setApplicationName("Libre.fm");
    QDeclarativeView view;
    //view.setSource(QUrl::fromLocalFile("src/librefm.qml"));
    view.setSource(QUrl::fromLocalFile(DATADIR "/librefm/librefm.qml"));
    QObject *root = (QObject*)(view.rootObject());

    ServerComm sc;
    view.rootContext()->setContextProperty("serverComm", &sc);
    QObject::connect(root, SIGNAL(login(QString, QString)), &sc, SLOT(login(QString, QString)));
    QObject::connect(root, SIGNAL(tuneStation(QString)), &sc, SLOT(tuneStation(QString)));
    QObject::connect(root, SIGNAL(tuneStationByName(QString)), &sc, SLOT(tuneStationByName(QString)));
    QObject::connect(root, SIGNAL(next()), &sc, SLOT(next()));
    QObject::connect(root, SIGNAL(prev()), &sc, SLOT(prev()));
    QObject::connect(root, SIGNAL(play()), &sc, SLOT(play()));
    QObject::connect(root, SIGNAL(pause()), &sc, SLOT(pause()));
    QObject::connect(root, SIGNAL(love()), &sc, SLOT(love()));
    QObject::connect(root, SIGNAL(ban()), &sc, SLOT(ban()));
    QObject::connect((QObject*)view.engine(), SIGNAL(quit()), &app, SLOT(quit()));

    view.showFullScreen();
    return app.exec();
}

